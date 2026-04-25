<?php

namespace App\Http\Controllers;

use App\Events\FileUploadStatusChanged;
use App\Http\Requests\UploadCsvRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\FileUploadResource;
use App\Jobs\ProcessCsvUpload;
use App\Models\AuditLog;
use App\Models\FileUpload;
use App\Services\CsvValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function index()
    {
        return view('uploads.index');
    }

    public function store(UploadCsvRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $storedFilename = 'uploads/' . Str::uuid() . '_' . $originalFilename;

        Storage::put($storedFilename, file_get_contents($file->getRealPath()));

        if (config('csv.validation_enabled', true)) {
            $validator = new CsvValidatorService();
            $result = $validator->validate(Storage::path($storedFilename));

            if (!$result['valid']) {
                Storage::delete($storedFilename);
                return response()->json([
                    'message' => 'CSV validation failed',
                    'errors' => $result['errors'],
                ], 422);
            }
        }

        $fileUpload = FileUpload::create([
            'filename' => $storedFilename,
            'original_filename' => $originalFilename,
            'status' => 'pending',
            'notification_email' => $request->notification_email,
        ]);

        AuditLog::log(
            'upload',
            'success',
            $fileUpload->id,
            "File uploaded: {$originalFilename}",
            [
                'filename' => $originalFilename,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]
        );

        dispatch(new ProcessCsvUpload($fileUpload, $request->notification_email));

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => new FileUploadResource($fileUpload),
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $query = FileUpload::query();

        if ($request->filled('search')) {
            $query->where('original_filename', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $sortBy = in_array($request->sort_by, ['created_at', 'original_filename', 'status', 'processed_rows'])
            ? $request->sort_by
            : 'created_at';

        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';
        $perPage = min((int) ($request->per_page ?? 15), 100);

        $uploads = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        $stats = FileUpload::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => FileUploadResource::collection($uploads),
            'meta' => [
                'current_page' => $uploads->currentPage(),
                'per_page' => $uploads->perPage(),
                'total' => $uploads->total(),
                'last_page' => $uploads->lastPage(),
                'from' => $uploads->firstItem(),
                'to' => $uploads->lastItem(),
            ],
            'stats' => [
                'total' => array_sum($stats),
                'pending' => $stats['pending'] ?? 0,
                'processing' => $stats['processing'] ?? 0,
                'completed' => $stats['completed'] ?? 0,
                'failed' => $stats['failed'] ?? 0,
            ],
        ]);
    }

    public function show(FileUpload $upload): JsonResponse
    {
        return response()->json([
            'data' => new FileUploadResource($upload),
        ]);
    }

    public function download(FileUpload $upload)
    {
        if (empty($upload->processed_file_path)) {
            return response()->json(['message' => 'Processed file not available'], 404);
        }

        $path = Storage::path($upload->processed_file_path);

        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        AuditLog::log(
            'download',
            'success',
            $upload->id,
            "Downloaded processed file: {$upload->original_filename}",
            ['filename' => $upload->original_filename]
        );

        return response()->download($path, 'processed_' . $upload->original_filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function retry(FileUpload $upload): JsonResponse
    {
        if (!in_array($upload->status, ['failed', 'completed'])) {
            return response()->json([
                'message' => 'Only failed or completed uploads can be retried',
            ], 422);
        }

        $upload->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);

        AuditLog::log(
            'retry',
            'success',
            $upload->id,
            "Upload queued for retry: {$upload->original_filename}",
            ['filename' => $upload->original_filename]
        );

        dispatch(new ProcessCsvUpload($upload, $upload->notification_email));

        return response()->json([
            'message' => 'Upload queued for retry',
            'data' => new FileUploadResource($upload->fresh()),
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $uploads = FileUpload::whereIn('id', $request->ids)->get();

        foreach ($uploads as $upload) {
            if ($upload->filename) {
                Storage::delete($upload->filename);
            }
            if ($upload->processed_file_path) {
                Storage::delete($upload->processed_file_path);
            }

            AuditLog::log(
                'delete',
                'success',
                $upload->id,
                "Upload deleted: {$upload->original_filename}",
                ['filename' => $upload->original_filename]
            );

            $upload->delete();
        }

        return response()->json([
            'message' => count($uploads) . ' upload(s) deleted successfully',
        ]);
    }

    public function bulkRetry(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $uploads = FileUpload::whereIn('id', $request->ids)
            ->whereIn('status', ['failed', 'completed'])
            ->get();

        foreach ($uploads as $upload) {
            $upload->update([
                'status' => 'pending',
                'error_message' => null,
                'processed_rows' => 0,
                'failed_rows' => 0,
                'started_at' => null,
                'completed_at' => null,
            ]);

            AuditLog::log(
                'retry',
                'success',
                $upload->id,
                "Upload queued for retry: {$upload->original_filename}",
                ['filename' => $upload->original_filename]
            );

            dispatch(new ProcessCsvUpload($upload, $upload->notification_email));
        }

        return response()->json([
            'message' => count($uploads) . ' upload(s) queued for retry',
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('fileUpload');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('file_upload_id')) {
            $query->where('file_upload_id', $request->file_upload_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = min((int) ($request->per_page ?? 50), 200);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function exportAuditLogs(Request $request)
    {
        $query = AuditLog::with('fileUpload');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $filename = 'audit_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $path = 'exports/' . $filename;

        Storage::makeDirectory('exports');
        $handle = fopen(Storage::path($path), 'w');

        fputcsv($handle, ['ID', 'File Upload ID', 'Filename', 'Action', 'Status', 'Details', 'IP Address', 'Created At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->file_upload_id,
                $log->fileUpload?->original_filename ?? 'N/A',
                $log->action,
                $log->status,
                $log->details,
                $log->ip_address,
                $log->created_at->toIso8601String(),
            ]);
        }

        fclose($handle);

        AuditLog::log('export', 'success', null, 'Audit logs exported', ['filename' => $filename]);

        return response()->download(Storage::path($path), $filename, ['Content-Type' => 'text/csv'])
            ->deleteFileAfterSend();
    }
}

<?php

namespace App\Jobs;

use App\Events\FileUploadStatusChanged;
use App\Mail\UploadCompletedMail;
use App\Models\AuditLog;
use App\Models\FileUpload;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessCsvUpload implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 3;

    public function __construct(
        public FileUpload $fileUpload,
        public ?string $notificationEmail = null
    ) {}

    public function handle(): void
    {
        try {
            $this->fileUpload->update([
                'status' => 'processing',
                'started_at' => now(),
                'processed_rows' => 0,
                'failed_rows' => 0,
            ]);

            broadcast(new FileUploadStatusChanged($this->fileUpload));

            $filePath = Storage::path($this->fileUpload->filename);

            if (!file_exists($filePath)) {
                throw new \RuntimeException("CSV file not found: {$filePath}");
            }

            $rows = $this->parseCsv($filePath);
            $totalRows = count($rows);

            $this->fileUpload->update(['total_rows' => $totalRows]);

            $processedRows = 0;
            $failedRows = 0;
            $processedData = [];

            foreach ($rows as $index => $row) {
                try {
                    $result = $this->upsertProduct($row);
                    $processedRows++;
                    $processedData[] = array_merge($row, ['status' => 'success', 'error' => '']);
                } catch (\Exception $e) {
                    $failedRows++;
                    $processedData[] = array_merge($row, ['status' => 'failed', 'error' => $e->getMessage()]);
                }

                if (($index + 1) % 50 === 0) {
                    $this->fileUpload->update([
                        'processed_rows' => $processedRows,
                        'failed_rows' => $failedRows,
                    ]);
                    broadcast(new FileUploadStatusChanged($this->fileUpload));
                }
            }

            $processedFilePath = $this->saveProcessedData($processedData);

            $this->fileUpload->update([
                'status' => 'completed',
                'processed_rows' => $processedRows,
                'failed_rows' => $failedRows,
                'processed_file_path' => $processedFilePath,
                'completed_at' => now(),
            ]);

            broadcast(new FileUploadStatusChanged($this->fileUpload));

            AuditLog::log(
                'process',
                'success',
                $this->fileUpload->id,
                "File processed successfully: {$processedRows} rows processed, {$failedRows} failed",
                [
                    'filename' => $this->fileUpload->original_filename,
                    'total_rows' => $totalRows,
                    'processed_rows' => $processedRows,
                    'failed_rows' => $failedRows,
                ]
            );

            $this->sendNotification(true);

        } catch (\Exception $e) {
            Log::error('CSV processing failed', [
                'file_upload_id' => $this->fileUpload->id,
                'error' => $e->getMessage(),
            ]);

            $this->fileUpload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            broadcast(new FileUploadStatusChanged($this->fileUpload));

            AuditLog::log(
                'process',
                'failed',
                $this->fileUpload->id,
                "File processing failed: {$e->getMessage()}",
                ['filename' => $this->fileUpload->original_filename]
            );

            $this->sendNotification(false);
        }
    }

    protected function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $header = null;

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(function ($col) {
                    $col = $this->cleanUtf8($col);
                    return preg_replace('/[\x00-\x1F\x7F]/u', '', trim($col));
                }, $line);
                continue;
            }

            if (count($line) !== count($header)) {
                $line = array_pad($line, count($header), '');
            }

            $row = array_combine($header, $line);
            $rows[] = array_map([$this, 'cleanUtf8'], $row);
        }

        fclose($handle);
        return $rows;
    }

    protected function cleanUtf8(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    protected function upsertProduct(array $row): Product
    {
        $uniqueKey = trim($row['UNIQUE_KEY'] ?? '');

        if (empty($uniqueKey)) {
            throw new \InvalidArgumentException('UNIQUE_KEY is required and cannot be empty');
        }

        $price = null;
        if (!empty($row['PIECE_PRICE'])) {
            $priceStr = str_replace(['$', ','], '', trim($row['PIECE_PRICE']));
            if (is_numeric($priceStr)) {
                $price = (float) $priceStr;
            }
        }

        return Product::updateOrCreate(
            ['unique_key' => $uniqueKey],
            [
                'product_title' => trim($row['PRODUCT_TITLE'] ?? ''),
                'product_description' => trim($row['PRODUCT_DESCRIPTION'] ?? ''),
                'style' => trim($row['STYLE#'] ?? ''),
                'sanmar_mainframe_color' => trim($row['SANMAR_MAINFRAME_COLOR'] ?? ''),
                'size' => trim($row['SIZE'] ?? ''),
                'color_name' => trim($row['COLOR_NAME'] ?? ''),
                'piece_price' => $price,
            ]
        );
    }

    protected function saveProcessedData(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $directory = 'processed';
        Storage::makeDirectory($directory);

        $filename = 'processed_' . $this->fileUpload->original_filename;
        $path = $directory . '/' . $filename;

        $handle = fopen(Storage::path($path), 'w');

        $headers = array_keys($data[0]);
        fputcsv($handle, $headers);

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    protected function sendNotification(bool $success): void
    {
        $email = $this->notificationEmail ?? $this->fileUpload->notification_email;

        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new UploadCompletedMail($this->fileUpload, $success));
            $this->fileUpload->update(['notification_sent' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'file_upload_id' => $this->fileUpload->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

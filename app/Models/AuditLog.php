<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_upload_id',
        'action',
        'status',
        'details',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function fileUpload()
    {
        return $this->belongsTo(FileUpload::class);
    }

    public static function log(
        string $action,
        string $status,
        ?int $fileUploadId = null,
        ?string $details = null,
        array $metadata = []
    ): self {
        return self::create([
            'file_upload_id' => $fileUploadId,
            'action' => $action,
            'status' => $status,
            'details' => $details,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}

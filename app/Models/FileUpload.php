<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileUpload extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'filename',
        'original_filename',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'error_message',
        'validation_errors',
        'processed_file_path',
        'notification_email',
        'started_at',
        'completed_at',
        'notification_sent',
    ];

    protected $casts = [
        'validation_errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'notification_sent' => 'boolean',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        if (!$this->total_rows || $this->total_rows === 0) {
            return 0;
        }
        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    public function getIsProcessingAttribute(): bool
    {
        return $this->status === 'processing';
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }
}

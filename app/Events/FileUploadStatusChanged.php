<?php

namespace App\Events;

use App\Models\FileUpload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileUploadStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FileUpload $fileUpload) {}

    public function broadcastOn(): Channel
    {
        return new Channel('file-uploads');
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->fileUpload->id,
            'status' => $this->fileUpload->status,
            'total_rows' => $this->fileUpload->total_rows,
            'processed_rows' => $this->fileUpload->processed_rows,
            'failed_rows' => $this->fileUpload->failed_rows,
            'progress_percentage' => $this->fileUpload->progress_percentage,
            'error_message' => $this->fileUpload->error_message,
            'completed_at' => $this->fileUpload->completed_at?->toIso8601String(),
            'has_processed_file' => !empty($this->fileUpload->processed_file_path),
        ];
    }
}

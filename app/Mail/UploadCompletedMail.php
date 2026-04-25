<?php

namespace App\Mail;

use App\Models\FileUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FileUpload $fileUpload,
        public bool $success = true
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->success
            ? 'CSV Upload Completed Successfully'
            : 'CSV Upload Failed';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.upload-completed');
    }

    public function attachments(): array
    {
        if (!$this->success || empty($this->fileUpload->processed_file_path)) {
            return [];
        }

        $path = Storage::path($this->fileUpload->processed_file_path);

        if (!file_exists($path)) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as('processed_' . $this->fileUpload->original_filename)
                ->withMime('text/csv'),
        ];
    }
}

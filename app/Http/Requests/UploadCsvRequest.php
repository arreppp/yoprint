<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSizeKb = config('csv.max_size', 10240);

        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                "max:{$maxSizeKb}",
            ],
            'notification_email' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to upload.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file size must not exceed ' . config('csv.max_size', 10240) . 'KB.',
            'notification_email.email' => 'Please provide a valid email address for notifications.',
        ];
    }
}

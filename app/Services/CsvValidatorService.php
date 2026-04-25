<?php

namespace App\Services;

class CsvValidatorService
{
    protected array $requiredColumns;
    protected int $maxSize;

    public function __construct()
    {
        $this->requiredColumns = array_map('trim', explode(',', config('csv.required_columns', 'UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SANMAR_MAINFRAME_COLOR,SIZE,COLOR_NAME,PIECE_PRICE')));
        $this->maxSize = config('csv.max_size', 10240);
    }

    public function validate(string $filePath): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            return ['valid' => false, 'errors' => ['File not found']];
        }

        $fileSizeKb = filesize($filePath) / 1024;
        if ($fileSizeKb > $this->maxSize) {
            $errors[] = "File size ({$fileSizeKb}KB) exceeds maximum allowed size ({$this->maxSize}KB)";
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['valid' => false, 'errors' => ['Cannot open file for reading']];
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['valid' => false, 'errors' => ['CSV file is empty or has no header row']];
        }

        $header = array_map('trim', $header);
        $header = array_map(function ($col) {
            return preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/u', '', $col);
        }, $header);

        $missingColumns = array_diff($this->requiredColumns, $header);
        if (!empty($missingColumns)) {
            $errors[] = 'Missing required columns: ' . implode(', ', $missingColumns);
        }

        $duplicates = array_diff_assoc($header, array_unique($header));
        if (!empty($duplicates)) {
            $errors[] = 'Duplicate column names found: ' . implode(', ', array_unique($duplicates));
        }

        if (!empty($errors)) {
            fclose($handle);
            return ['valid' => false, 'errors' => $errors];
        }

        $headerIndex = array_flip($header);
        $rowCount = 0;
        $sampleErrors = [];

        while (($row = fgetcsv($handle)) !== false && $rowCount < 100) {
            $rowCount++;
            $data = array_combine($header, array_pad($row, count($header), ''));

            $uniqueKey = trim($data['UNIQUE_KEY'] ?? '');
            if (empty($uniqueKey)) {
                $sampleErrors[] = "Row {$rowCount}: UNIQUE_KEY is empty";
            }

            if (isset($data['PIECE_PRICE']) && $data['PIECE_PRICE'] !== '') {
                $price = str_replace(['$', ','], '', trim($data['PIECE_PRICE']));
                if (!is_numeric($price)) {
                    $sampleErrors[] = "Row {$rowCount}: PIECE_PRICE '{$data['PIECE_PRICE']}' is not numeric";
                }
            }
        }

        fclose($handle);

        if ($rowCount === 0) {
            $errors[] = 'CSV file has no data rows';
        }

        if (!empty($sampleErrors)) {
            $errors = array_merge($errors, array_slice($sampleErrors, 0, 10));
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

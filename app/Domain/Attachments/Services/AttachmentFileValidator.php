<?php

namespace App\Domain\Attachments\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class AttachmentFileValidator
{
    public const MAX_BYTES = 10_485_760;

    /** @var array<string, list<string>> */
    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ],
    ];

    /**
     * @return array{original_name: string, extension: string, mime_type: string, size: int, storage_name: string}
     */
    public function validate(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->fail('The file upload did not complete successfully.');
        }

        $originalName = $file->getClientOriginalName();
        $this->validateName($originalName);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (! array_key_exists($extension, self::MIME_TYPES)) {
            $this->fail('The file extension is not allowed.');
        }

        $size = $file->getSize();
        if (! is_int($size) || $size < 1) {
            $this->fail('The file must not be empty.');
        }
        if ($size > self::MAX_BYTES) {
            $this->fail('The file may not exceed 10 MiB.');
        }

        $path = $file->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            $this->fail('The uploaded file is not available.');
        }
        $mimeType = strtolower((string) $file->getMimeType());
        if (! in_array($mimeType, self::MIME_TYPES[$extension], true)) {
            $this->fail('The file content does not match its extension.');
        }

        if ($extension === 'csv') {
            $sample = file_get_contents($path, false, null, 0, min($size, 8192));
            if (! is_string($sample) || str_contains($sample, "\0")) {
                $this->fail('The CSV content is not valid text.');
            }
        }
        if (in_array($extension, ['xlsx', 'docx'], true)) {
            $this->validateOoxml($path, $extension);
        }

        return [
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
            'storage_name' => (string) Str::uuid().'.'.$extension,
        ];
    }

    private function validateName(string $name): void
    {
        if ($name === ''
            || ! mb_check_encoding($name, 'UTF-8')
            || mb_strlen($name) > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, '..')) {
            $this->fail('The file name is not allowed.');
        }

        $stem = trim((string) pathinfo($name, PATHINFO_FILENAME), " \t\n\r\0\x0B.-_");
        if ($stem === '') {
            $this->fail('The file name must contain a usable name.');
        }
    }

    private function validateOoxml(string $path, string $extension): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('The Office document is not a valid OOXML file.');
        }

        try {
            $documentEntry = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';
            if ($zip->locateName('[Content_Types].xml') === false
                || $zip->locateName($documentEntry) === false) {
                $this->fail('The Office document content does not match its extension.');
            }
        } finally {
            $zip->close();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}

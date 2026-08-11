<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

trait CreatesAttachmentFiles
{
    /** @var list<string> */
    private array $attachmentTemporaryFiles = [];

    protected function attachmentFile(string $extension, ?string $name = null): UploadedFile
    {
        $extension = strtolower($extension);
        $name ??= 'document.'.$extension;

        return match ($extension) {
            'pdf' => $this->uploadedFile($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n", 'application/pdf'),
            'jpg', 'jpeg' => $this->uploadedFile($name, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9k=', true) ?: throw new RuntimeException('Invalid JPEG fixture.'), 'image/jpeg'),
            'png' => $this->uploadedFile($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true) ?: throw new RuntimeException('Invalid PNG fixture.'), 'image/png'),
            'csv' => $this->uploadedFile($name, "voce,importo\nDemo,10.00\n", 'text/csv'),
            'xlsx' => $this->ooxmlFile($name, 'xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'docx' => $this->ooxmlFile($name, 'word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            default => $this->uploadedFile($name, 'unsupported', 'application/octet-stream'),
        };
    }

    protected function uploadedFile(string $name, string $contents, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mpit-attachment-');
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to create attachment test fixture.');
        }
        $this->attachmentTemporaryFiles[] = $path;

        return new class($path, $name, $clientMime) extends UploadedFile
        {
            public function __construct(string $path, private readonly string $fixtureOriginalName, string $clientMime)
            {
                parent::__construct($path, $fixtureOriginalName, $clientMime, UPLOAD_ERR_OK, true);
            }

            public function getClientOriginalName(): string
            {
                return $this->fixtureOriginalName;
            }
        };
    }

    protected function cleanupAttachmentFiles(): void
    {
        foreach ($this->attachmentTemporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->attachmentTemporaryFiles = [];
    }

    private function ooxmlFile(string $name, string $documentEntry, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mpit-ooxml-');
        if ($path === false) {
            throw new RuntimeException('Unable to create OOXML test fixture.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open OOXML test fixture.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString($documentEntry, '<?xml version="1.0"?><document/>');
        $zip->close();
        $this->attachmentTemporaryFiles[] = $path;

        return new class($path, $name, $clientMime) extends UploadedFile
        {
            public function __construct(string $path, private readonly string $fixtureOriginalName, string $clientMime)
            {
                parent::__construct($path, $fixtureOriginalName, $clientMime, UPLOAD_ERR_OK, true);
            }

            public function getClientOriginalName(): string
            {
                return $this->fixtureOriginalName;
            }
        };
    }
}

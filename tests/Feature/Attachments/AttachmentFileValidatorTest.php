<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Services\AttachmentFileValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesAttachmentFiles;
use Tests\TestCase;

class AttachmentFileValidatorTest extends TestCase
{
    use CreatesAttachmentFiles;

    protected function tearDown(): void
    {
        $this->cleanupAttachmentFiles();
        parent::tearDown();
    }

    public function test_every_allowed_format_is_detected_from_server_content(): void
    {
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'csv', 'xlsx', 'docx'] as $extension) {
            $validated = app(AttachmentFileValidator::class)->validate($this->attachmentFile($extension));

            $this->assertSame($extension, $validated['extension']);
            $this->assertGreaterThan(0, $validated['size']);
            $this->assertStringEndsWith('.'.$extension, $validated['storage_name']);
        }
    }

    public function test_empty_and_exactly_over_limit_files_are_rejected(): void
    {
        $this->assertInvalid($this->uploadedFile('empty.pdf', '', 'application/pdf'));

        $tooLarge = UploadedFile::fake()->create('large.pdf', 10_241, 'application/pdf');
        $this->assertInvalid($tooLarge);
    }

    public function test_forbidden_extension_and_significant_mime_mismatch_are_rejected(): void
    {
        $this->assertInvalid($this->uploadedFile('archive.zip', 'PK fixture', 'application/zip'));
        $this->assertInvalid($this->uploadedFile('renamed.pdf', "voce,importo\nDemo,10.00\n", 'application/pdf'));
    }

    public function test_dangerous_filenames_are_rejected(): void
    {
        foreach (['../document.pdf', '..\\document.pdf', "bad\0name.pdf", '...pdf', '/document.pdf'] as $name) {
            $this->assertInvalid($this->attachmentFile('pdf', $name));
        }
    }

    public function test_binary_csv_and_ooxml_of_the_wrong_kind_are_rejected(): void
    {
        $this->assertInvalid($this->uploadedFile('binary.csv', "a,b\0c,d", 'text/csv'));
        $this->assertInvalid($this->attachmentFile('docx', 'renamed.xlsx'));
        $this->assertInvalid($this->attachmentFile('xlsx', 'renamed.docx'));
    }

    private function assertInvalid(UploadedFile $file): void
    {
        try {
            app(AttachmentFileValidator::class)->validate($file);
            $this->fail('Invalid attachment was accepted: '.$file->getClientOriginalName());
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}

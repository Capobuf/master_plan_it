<?php

namespace Tests\Feature\Attachments;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Media;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class AttachmentSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_media_schema_has_explicit_tenant_uploader_and_package_columns(): void
    {
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertTrue(Schema::hasColumns('media', [
            'id', 'tenant_id', 'uploaded_by_user_id', 'model_type', 'model_id', 'uuid',
            'collection_name', 'name', 'file_name', 'mime_type', 'disk', 'conversions_disk',
            'size', 'manipulations', 'custom_properties', 'generated_conversions',
            'responsive_images', 'order_column', 'created_at', 'updated_at',
        ]));

        $indexes = collect(DB::select('SHOW INDEX FROM media'))->pluck('Key_name')->unique()->all();
        $this->assertContains('media_tenant_parent_collection_index', $indexes);
        $this->assertContains('media_model_type_model_id_index', $indexes);
        $this->assertContains('media_uuid_unique', $indexes);

        $foreignColumns = collect(DB::select(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media' AND REFERENCED_TABLE_NAME IS NOT NULL",
        ))->pluck('COLUMN_NAME')->all();
        $this->assertContains('tenant_id', $foreignColumns);
        $this->assertContains('uploaded_by_user_id', $foreignColumns);
    }

    public function test_custom_media_model_and_supported_parents_use_only_private_collection(): void
    {
        $this->assertSame(Media::class, config('media-library.media_model'));
        $this->assertSame('attachments', config('media-library.disk_name'));
        $this->assertSame(storage_path('app/private/attachments'), config('filesystems.disks.attachments.root'));
        $this->assertTrue(config('filesystems.disks.attachments.throw'));
        $this->assertFalse(config('filesystems.disks.attachments.serve'));
        $this->assertSame(0640, config('filesystems.disks.attachments.permissions.file.private'));
        $this->assertSame(0770, config('filesystems.disks.attachments.permissions.dir.private'));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(Media::class));

        foreach ([Expense::class, ExpenseRow::class, Contract::class, Project::class] as $parentClass) {
            $this->assertTrue(is_subclass_of($parentClass, HasMedia::class));
            $parent = new $parentClass;
            $collection = $parent->getMediaCollection('attachments');
            $this->assertNotNull($collection);
            $this->assertSame('attachments', $collection->diskName);
        }
    }

    public function test_media_relations_resolve_tenant_uploader_and_exact_parent(): void
    {
        $tenant = Tenant::factory()->create();
        $uploader = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create();
        $media = Media::query()->create([
            'tenant_id' => $tenant->getKey(),
            'uploaded_by_user_id' => $uploader->getKey(),
            'model_type' => $expense->getMorphClass(),
            'model_id' => $expense->getKey(),
            'collection_name' => 'attachments',
            'name' => 'documento.pdf',
            'file_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'attachments',
            'conversions_disk' => 'attachments',
            'size' => 12,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $this->assertSame($tenant->getKey(), $media->tenant->getKey());
        $this->assertSame($uploader->getKey(), $media->uploadedBy->getKey());
        $this->assertSame($expense->getKey(), $media->model->getKey());
        $this->assertSame([$media->getKey()], $tenant->media()->pluck('media.id')->all());
        $this->assertSame('documento.pdf', $media->getDownloadFilename());
    }
}

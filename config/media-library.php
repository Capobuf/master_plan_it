<?php

use App\Domain\Attachments\Services\ThrowingFileRemover;
use App\Models\Media;

return [
    'disk_name' => 'attachments',
    'max_file_size' => 10_485_760,
    'media_model' => Media::class,
    'queue_conversions_by_default' => false,
    'queue_conversions_after_database_commit' => true,
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'csv', 'xlsx', 'docx'],
    'file_remover_class' => ThrowingFileRemover::class,
];

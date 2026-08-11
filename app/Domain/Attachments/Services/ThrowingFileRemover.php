<?php

namespace App\Domain\Attachments\Services;

use DomainException;
use Illuminate\Contracts\Filesystem\Factory;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\FileRemover;
use Throwable;

final class ThrowingFileRemover implements FileRemover
{
    public function __construct(
        private readonly Filesystem $mediaFilesystem,
        private readonly Factory $filesystem,
    ) {}

    public function removeAllFiles(Media $media): void
    {
        try {
            foreach (array_unique(array_filter([$media->disk, $media->conversions_disk])) as $disk) {
                $directory = $this->mediaFilesystem->getMediaDirectory($media);
                $driver = $this->filesystem->disk($disk);
                if ($driver->exists($directory) && ! $driver->deleteDirectory($directory)) {
                    throw new DomainException('ATTACHMENT_STORAGE_FAILURE');
                }
            }
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DomainException('ATTACHMENT_STORAGE_FAILURE', previous: $exception);
        }
    }

    public function removeResponsiveImages(Media $media, string $conversionName): void
    {
        $this->removeAllFiles($media);
    }

    public function removeFile(string $path, string $disk): void
    {
        try {
            $driver = $this->filesystem->disk($disk);
            if ($driver->exists($path) && ! $driver->delete($path)) {
                throw new DomainException('ATTACHMENT_STORAGE_FAILURE');
            }
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DomainException('ATTACHMENT_STORAGE_FAILURE', previous: $exception);
        }
    }
}

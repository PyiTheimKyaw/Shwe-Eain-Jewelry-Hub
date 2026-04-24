<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function storeImage(UploadedFile $file, string $folder): array
    {
        $disk = $this->writeDisk();
        $cleanFolder = trim($folder, '/');
        $path = $file->store($cleanFolder, $disk);

        $url = $this->publicUrl($path, $disk);

        return [
            'disk' => $disk,
            // Keep local records as relative paths; store firebase records as absolute URL
            // so legacy consumers can keep rendering with minimal changes.
            'storage_path' => $disk === 'firebase' ? $url : $path,
            'path' => $path,
            'url' => $url,
            'mime_type' => (string) $file->getMimeType(),
        ];
    }

    public function publicUrl(?string $value, ?string $disk = null): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if ($this->isAbsoluteUrl($raw)) {
            return $raw;
        }

        $useDisk = $disk ?: $this->readDiskForPath($raw);
        /** @var FilesystemAdapter $fs */
        $fs = Storage::disk($useDisk);
        $url = (string) $fs->url($raw);

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        return url($url);
    }

    protected function writeDisk(): string
    {
        $provider = strtolower((string) config('services.media.provider', 'local'));

        if ($provider === 'firebase') {
            return 'firebase';
        }

        return 'public';
    }

    protected function readDiskForPath(string $path): string
    {
        // Legacy rows are relative public-disk paths.
        return 'public';
    }

    protected function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

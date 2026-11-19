<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaStorageService
{
    public function writeDiskName(): string
    {
        return $this->writeDisk();
    }

    public function storeImage(UploadedFile $file, string $folder): array
    {
        $disk = $this->writeDisk();
        $cleanFolder = trim($folder, '/');
        $path = $file->store($cleanFolder, $disk);

        $url = $this->publicUrl($path, $disk);

        return [
            'disk' => $disk,
            'storage_path' => $path,
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

    public function deleteByStoragePath(?string $value): void
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return;
        }

        if ($this->isAbsoluteUrl($raw)) {
            $firebasePublicBase = trim((string) config('filesystems.disks.firebase.url', ''), '/');
            if ($firebasePublicBase !== '' && str_starts_with($raw, $firebasePublicBase.'/')) {
                $relative = ltrim(substr($raw, strlen($firebasePublicBase)), '/');
                Storage::disk('firebase')->delete($relative);

                return;
            }

            return;
        }

        Storage::disk($this->writeDisk())->delete($raw);
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
        return $this->writeDisk();
    }

    protected function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

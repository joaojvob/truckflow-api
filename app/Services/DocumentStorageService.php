<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    public const DISK = 'private';

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public function replace(UploadedFile $file, string $directory, ?string $oldPath): string
    {
        $path = $this->store($file, $directory);
        $this->delete($oldPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function download(?string $path, string $filename): StreamedResponse
    {
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404, 'Documento não encontrado.');

        return Storage::disk(self::DISK)->download($path, $filename);
    }

    public function has(?string $path): bool
    {
        return $path && Storage::disk(self::DISK)->exists($path);
    }
}

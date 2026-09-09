<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CatalogFileService
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    public function replace(?string $currentPath, ?UploadedFile $file, string $directory, bool $remove = false): ?string
    {
        if ($remove) {
            $this->delete($currentPath);

            return null;
        }

        if (! $file) {
            return $currentPath;
        }

        $path = $this->store($file, $directory);
        $this->delete($currentPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}

<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandService
{
    public function __construct(private CatalogFileService $files) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $logo = null): Brand
    {
        return DB::transaction(function () use ($data, $logo) {
            $brand = Brand::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name']),
                'description' => $data['description'] ?? null,
                'logo' => $logo ? $this->files->store($logo, 'brands') : null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $brand;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data, ?UploadedFile $logo = null): Brand
    {
        if (array_key_exists('name', $data)) {
            $brand->name = $data['name'];
        }

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $source = $data['slug'] ?? $brand->name;
            $brand->slug = $this->uniqueSlug($source, $brand->name, $brand->id);
        }

        if (array_key_exists('description', $data)) {
            $brand->description = $data['description'];
        }

        if (array_key_exists('is_active', $data)) {
            $brand->is_active = (bool) $data['is_active'];
        }

        $brand->logo = $this->files->replace(
            $brand->logo,
            $logo,
            'brands',
            (bool) ($data['remove_logo'] ?? false),
        );

        $brand->save();

        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        if ($brand->devices()->exists()) {
            throw ValidationException::withMessages([
                'brand' => ['This brand cannot be deleted because devices are still assigned to it.'],
            ]);
        }

        $this->files->delete($brand->logo);
        $brand->delete();
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        if ($base === '') {
            $base = 'brand';
        }

        $candidate = $base;
        $suffix = 2;

        while (
            Brand::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}

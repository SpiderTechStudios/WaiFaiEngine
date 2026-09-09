<?php

namespace App\Services;

use App\Models\DeviceCategory;
use Illuminate\Validation\ValidationException;

class DeviceCategoryService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DeviceCategory
    {
        return DeviceCategory::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DeviceCategory $category, array $data): DeviceCategory
    {
        $category->fill([
            'name' => $data['name'] ?? $category->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
        ])->save();

        return $category->fresh();
    }

    public function delete(DeviceCategory $category): void
    {
        if ($category->devices()->exists()) {
            throw ValidationException::withMessages([
                'device_category' => ['This category cannot be deleted because devices are still assigned to it.'],
            ]);
        }

        $category->delete();
    }
}

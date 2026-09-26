<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseTypeService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ExpenseType
    {
        $slug = $this->uniqueSlug($data['name']);

        return DB::transaction(function () use ($data, $slug, $actor) {
            $type = ExpenseType::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->auditLogger->log('expense_type_created', $actor, null, ExpenseType::class, $type->id);

            return $type;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseType $type, array $data, User $actor): ExpenseType
    {
        $attributes = [];

        if (array_key_exists('name', $data) && $data['name'] !== $type->name) {
            $attributes['name'] = $data['name'];
            $attributes['slug'] = $this->uniqueSlug($data['name'], $type->id);
        }

        foreach (['description', 'category', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        return DB::transaction(function () use ($type, $attributes, $actor) {
            $type->fill($attributes)->save();

            $this->auditLogger->log('expense_type_updated', $actor, null, ExpenseType::class, $type->id);

            return $type->fresh();
        });
    }

    public function delete(ExpenseType $type, User $actor): void
    {
        $inUse = Expense::withTrashed()->where('expense_type_id', $type->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'expense_type' => ['This expense type is used by existing expenses. Deactivate it instead of deleting.'],
            ]);
        }

        DB::transaction(function () use ($type, $actor) {
            $this->auditLogger->log('expense_type_deleted', $actor, null, ExpenseType::class, $type->id);

            $type->delete();
        });
    }

    public function setActive(ExpenseType $type, bool $active, User $actor): ExpenseType
    {
        $type->forceFill(['is_active' => $active])->save();

        $this->auditLogger->log(
            $active ? 'expense_type_activated' : 'expense_type_deactivated',
            $actor,
            null,
            ExpenseType::class,
            $type->id,
        );

        return $type->fresh();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            ExpenseType::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(
        string $action,
        ?User $user = null,
        ?int $companyId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): void {
        $request ??= request();

        AuditLog::query()->create([
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

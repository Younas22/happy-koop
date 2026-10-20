<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records sensitive admin actions.
 *
 * Only changed key NAMES are stored — never values — so a secret can never
 * leak into the audit trail (api-contract.md 8.2).
 */
class AuditLogger
{
    /**
     * @param  list<string>  $changedKeys
     */
    public function log(
        string $action,
        ?int $userId = null,
        ?Model $subject = null,
        array $changedKeys = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changed_keys' => array_values(array_unique($changedKeys)),
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512) ?: null,
            'created_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;

final class AuditLogResource
{
    /**
     * Changed key NAMES only — never values (api-contract.md 8.2).
     *
     * @return array<string, mixed>
     */
    public static function make(AuditLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'action' => $log->action,
            'actor' => $log->user === null ? null : [
                'id' => (int) $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ],
            'subjectType' => $log->subject_type === null ? null : class_basename($log->subject_type),
            'subjectId' => $log->subject_id === null ? null : (int) $log->subject_id,
            'changedKeys' => array_values((array) ($log->changed_keys ?? [])),
            'ip' => $log->ip,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }
}

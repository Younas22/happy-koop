<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $this->validateQuery($request, [
            'action' => ['sometimes', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = AuditLog::query()->with('user')->orderByDesc('id');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], (string) $action).'%');
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (AuditLog $log): array => AuditLogResource::make($log)
        );
    }
}

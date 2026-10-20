<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\AuditLogger;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends ApiController
{
    public function __construct(
        private readonly MediaUploadService $uploads,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $this->validateQuery($request, [
            'type' => ['sometimes', 'string', 'in:image,video'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:80'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = Media::query()->orderByDesc('id');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($folder = $request->query('folder')) {
            $query->where('folder', $folder);
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (Media $media): array => MediaResource::upload($media)
        );
    }

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $media = $this->uploads->store(
            $request->file('file'),
            $request->input('alt'),
            $request->input('folder'),
            $request->user()
        );

        $this->audit->log('media.uploaded', $request->user()?->id, $media, ['path'], $request);

        return $this->data(MediaResource::upload($media), 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Media|null $media */
        $media = Media::query()->find($id);

        abort_if($media === null, 404);

        $this->authorize('delete', $media);

        $this->audit->log('media.deleted', $request->user()?->id, $media, ['path'], $request);

        $this->uploads->delete($media);

        return response()->json(null, 204);
    }
}

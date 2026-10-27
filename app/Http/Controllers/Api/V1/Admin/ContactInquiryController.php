<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Models\ContactInquiry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Messages sent through the storefront contact form.
 *
 * The record is the source of truth (the emails are only copies), so this is
 * where an admin reads them, marks them handled and deletes them. The IP
 * address and user agent stay hidden.
 */
class ContactInquiryController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContactInquiry::class);

        $this->validateQuery($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'string', 'in:new,handled'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = ContactInquiry::query()->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            });
        }

        $response = $this->paginated(
            $query->paginate($this->perPage($request, 25)),
            fn (ContactInquiry $inquiry): array => $this->resource($inquiry, true)
        );

        // The unread count, whatever the filter, for the page header.
        $payload = $response->getData(true);
        $payload['meta']['newCount'] = ContactInquiry::query()->where('status', ContactInquiry::STATUS_NEW)->count();

        return response()->json($payload);
    }

    public function show(int $id): JsonResponse
    {
        $inquiry = $this->find($id);

        $this->authorize('view', $inquiry);

        return $this->data($this->resource($inquiry, false));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $inquiry = $this->find($id);

        $this->authorize('update', $inquiry);

        $status = $this->validatedStatus($request);

        $inquiry->forceFill([
            'status' => $status,
            'handled_at' => $status === ContactInquiry::STATUS_HANDLED ? ($inquiry->handled_at ?? now()) : null,
        ])->save();

        $this->audit->log('contact_inquiry.updated', $request->user()?->id, $inquiry, ['status'], $request);

        return $this->data($this->resource($inquiry->refresh(), false));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $inquiry = $this->find($id);

        $this->authorize('delete', $inquiry);

        $inquiry->delete();

        $this->audit->log('contact_inquiry.deleted', $request->user()?->id, $inquiry, ['id'], $request);

        return response()->json(null, 204);
    }

    public function bulkDestroy(BulkIdsRequest $request): JsonResponse
    {
        $deleted = [];

        foreach (ContactInquiry::query()->whereIn('id', $request->ids())->get() as $inquiry) {
            $this->authorize('delete', $inquiry);

            $inquiry->delete();
            $deleted[] = (int) $inquiry->id;

            $this->audit->log('contact_inquiry.deleted', $request->user()?->id, $inquiry, ['id'], $request);
        }

        return $this->data(['succeeded' => $deleted, 'failed' => []]);
    }

    public function bulkUpdate(BulkIdsRequest $request): JsonResponse
    {
        $status = $this->validatedStatus($request);
        $updated = [];

        foreach (ContactInquiry::query()->whereIn('id', $request->ids())->get() as $inquiry) {
            $this->authorize('update', $inquiry);

            $inquiry->forceFill([
                'status' => $status,
                'handled_at' => $status === ContactInquiry::STATUS_HANDLED ? ($inquiry->handled_at ?? now()) : null,
            ])->save();
            $updated[] = (int) $inquiry->id;

            $this->audit->log('contact_inquiry.updated', $request->user()?->id, $inquiry, ['status'], $request);
        }

        return $this->data(['succeeded' => $updated, 'failed' => []]);
    }

    private function validatedStatus(Request $request): string
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.ContactInquiry::STATUS_NEW.','.ContactInquiry::STATUS_HANDLED],
        ]);

        return (string) $data['status'];
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(ContactInquiry $inquiry, bool $summary): array
    {
        $message = (string) $inquiry->message;

        return [
            'id' => (int) $inquiry->id,
            'reference' => $inquiry->reference,
            'name' => $inquiry->name,
            'email' => $inquiry->email,
            'subject' => $inquiry->subject,
            'status' => $inquiry->status,
            'userId' => $inquiry->user_id === null ? null : (int) $inquiry->user_id,
            'receivedAt' => $inquiry->created_at?->toIso8601String(),
            'handledAt' => $inquiry->handled_at?->toIso8601String(),
        ] + ($summary
            ? ['preview' => mb_strimwidth(preg_replace('/\s+/', ' ', $message) ?? '', 0, 140, '…')]
            : ['message' => $message]);
    }

    private function find(int $id): ContactInquiry
    {
        /** @var ContactInquiry|null $inquiry */
        $inquiry = ContactInquiry::query()->find($id);

        abort_if($inquiry === null, 404);

        return $inquiry;
    }
}

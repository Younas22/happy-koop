<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Events\ContactInquiryReceived;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\ContactRequest;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ContactController extends ApiController
{
    /**
     * Accepts a contact-form submission.
     *
     * The order of operations is the whole point: the inquiry is written inside
     * a transaction and the notification event is dispatched after that
     * transaction commits (`ShouldDispatchAfterCommit`). A mail transport that
     * is down therefore cannot lose a visitor's message - it is already in the
     * database, and the queued emails retry on their own.
     *
     * The response is 201 with the reference, and it is returned only after the
     * inquiry has been accepted. Nothing is ever silently discarded: a failure
     * to store raises, and the storefront shows an error.
     *
     * The visitor never names a recipient. Rate limiting lives on the route,
     * and a filled honeypot is a 422 from the Form Request.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        $input = $request->validated();

        $inquiry = DB::transaction(static function () use ($input, $request): ContactInquiry {
            return ContactInquiry::query()->create([
                'reference' => ContactInquiry::newReference(),
                // Already sanitized and length-checked by the Form Request.
                'name' => (string) $input['name'],
                'email' => strtolower(trim((string) $input['email'])),
                'subject' => $input['subject'] ?? null,
                'message' => (string) $input['message'],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'user_id' => $request->user()?->id,
                'status' => ContactInquiry::STATUS_NEW,
            ]);
        });

        ContactInquiryReceived::dispatch($inquiry);

        return $this->data([
            'reference' => $inquiry->reference,
            'receivedAt' => $inquiry->created_at?->toIso8601String(),
        ], 201);
    }
}

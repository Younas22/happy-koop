<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\MailSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mail\MailSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailSettingsController extends ApiController
{
    public function __construct(private readonly MailSettingsService $mail) {}

    /** Status only — the API key is never returned, in any field. */
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return $this->data($this->mail->status());
    }

    public function update(MailSettingsRequest $request): JsonResponse
    {
        $this->authorize('update', new Setting);

        /** @var User $actor */
        $actor = $request->user();

        return $this->data($this->mail->update($request->validated(), $actor));
    }

    /**
     * Sends a test email to `to`, or to the signed-in admin.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorize('update', new Setting);

        /** @var User $actor */
        $actor = $request->user();

        $to = trim((string) $request->input('to', ''));
        $to = $to !== '' ? $to : (string) $actor->email;

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw ApiException::validation(['to' => ['Enter a valid email address.']]);
        }

        return $this->data($this->mail->sendTest($to, $actor));
    }
}

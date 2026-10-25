<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact inquiries submitted from the storefront's contact form.
 *
 * The message is persisted BEFORE either email is queued, so a mail transport
 * failure can never lose what a visitor wrote - the inquiry is still in the
 * admin's hands. `reference` is the short code quoted back to the visitor and
 * printed in the administrator's notification.
 *
 * Additive only: this migration creates one new table and touches nothing
 * else. `down()` drops only that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->string('name', 160);
            $table->string('email');
            $table->string('subject', 180)->nullable();
            $table->text('message');

            // Kept for abuse handling only, never shown to the visitor.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 24)->default('new');
            $table->timestamp('handled_at')->nullable();

            // Set once the notification emails have been queued, so a retried
            // submission cannot produce a second pair of emails.
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};

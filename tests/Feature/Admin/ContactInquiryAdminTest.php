<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ContactInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsStoreFixtures;
use Tests\TestCase;

/**
 * Contact form messages in the admin panel: list, read, mark handled, delete.
 */
class ContactInquiryAdminTest extends TestCase
{
    use BuildsStoreFixtures, RefreshDatabase;

    private function inquiry(array $attributes = []): ContactInquiry
    {
        return ContactInquiry::query()->create($attributes + [
            'reference' => ContactInquiry::newReference(),
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'subject' => 'Shipping question',
            'message' => 'Does this ship to the Netherlands?',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Test',
            'status' => ContactInquiry::STATUS_NEW,
        ]);
    }

    public function test_admin_lists_messages_with_the_unread_count_and_no_ip(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $this->inquiry();
        $this->inquiry(['name' => 'Bob', 'status' => ContactInquiry::STATUS_HANDLED]);

        $response = $this->getJson('/api/v1/admin/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.newCount', 1)
            ->assertJsonPath('data.1.preview', 'Does this ship to the Netherlands?');

        $this->assertStringNotContainsString('203.0.113.9', (string) $response->getContent());

        $this->getJson('/api/v1/admin/messages?status=new&q=ada')->assertJsonCount(1, 'data');
    }

    public function test_admin_reads_a_message_and_marks_it_handled(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $inquiry = $this->inquiry();

        $this->getJson('/api/v1/admin/messages/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('data.message', 'Does this ship to the Netherlands?');

        $this->patchJson('/api/v1/admin/messages/'.$inquiry->id, ['status' => 'handled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'handled');

        $this->assertNotNull($inquiry->refresh()->handled_at);

        $this->patchJson('/api/v1/admin/messages/'.$inquiry->id, ['status' => 'new'])->assertOk();
        $this->assertNull($inquiry->refresh()->handled_at);
    }

    public function test_admin_deletes_one_or_several_messages(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $a = $this->inquiry();
        $b = $this->inquiry();
        $c = $this->inquiry();

        $this->deleteJson('/api/v1/admin/messages/'.$a->id)->assertNoContent();
        $this->postJson('/api/v1/admin/messages/bulk-delete', ['ids' => [$b->id, $c->id]])->assertOk();

        $this->assertSame(0, ContactInquiry::query()->count());
    }

    public function test_admin_marks_several_messages_handled(): void
    {
        Sanctum::actingAs($this->adminUser(), ['customer', 'admin']);
        $a = $this->inquiry();
        $b = $this->inquiry();

        $this->patchJson('/api/v1/admin/messages/bulk-status', ['ids' => [$a->id, $b->id], 'status' => 'handled'])
            ->assertOk()
            ->assertJsonPath('data.succeeded', [$a->id, $b->id]);

        $this->assertSame(2, ContactInquiry::query()->where('status', 'handled')->count());
    }

    public function test_a_customer_cannot_read_messages(): void
    {
        $this->seedStore();
        $inquiry = $this->inquiry();
        Sanctum::actingAs($this->customerUser(), ['customer']);

        $this->getJson('/api/v1/admin/messages')->assertForbidden();
        $this->getJson('/api/v1/admin/messages/'.$inquiry->id)->assertForbidden();
    }
}

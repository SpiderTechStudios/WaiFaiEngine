<?php

namespace Tests\Feature;

use App\Models\SmsDeliveryReport;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NextSmsTest extends TestCase
{
    public function test_superadmin_can_send_single_sms_and_it_is_persisted(): void
    {
        Http::fake([
            'messaging-service.co.tz/*' => Http::response([
                'messages' => [[
                    'to' => '255744123456',
                    'status' => [
                        'groupId' => 1,
                        'groupName' => 'PENDING',
                        'name' => 'PENDING_ENROUTE',
                    ],
                    'messageId' => 'msg-single-1',
                ]],
            ], 200),
        ]);

        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/sms/send', [
                'to' => '0744123456',
                'text' => 'Hello single',
                'reference' => 'REF-SINGLE-1',
            ])->assertOk()
            ->assertJsonPath('data.messages.0.provider_message_id', 'msg-single-1')
            ->assertJsonPath('data.messages.0.recipient', '255744123456')
            ->assertJsonPath('data.messages.0.status', 'pending');

        $this->assertDatabaseHas('sms_messages', [
            'provider' => 'nextsms',
            'provider_message_id' => 'msg-single-1',
            'reference' => 'REF-SINGLE-1',
            'status' => 'pending',
        ]);
    }

    public function test_superadmin_can_broadcast_sms_to_recipients(): void
    {
        Http::fake(function ($request) {
            $recipients = $request['to'] ?? [];
            $recipients = is_array($recipients) ? $recipients : [$recipients];

            return Http::response([
                'messages' => collect($recipients)->map(fn ($to) => [
                    'to' => $to,
                    'status' => ['groupName' => 'PENDING', 'name' => 'PENDING_ENROUTE'],
                    'messageId' => 'msg-'.$to,
                ])->all(),
            ], 200);
        });

        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->postJson('/api/v1/superadmin/sms/broadcast', [
                'recipients' => ['0744111111', '0744222222'],
                'text' => 'Hello bulk',
                'reference' => 'REF-MULTI-1',
            ])->assertOk()
            ->assertJsonPath('data.recipients_count', 2)
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.status', 'pending');

        $this->assertSame(2, SmsMessage::query()->where('reference', 'REF-MULTI-1')->count());
    }

    public function test_delivery_report_callback_updates_message_status(): void
    {
        $message = SmsMessage::query()->create([
            'provider' => 'nextsms',
            'provider_message_id' => 'msg-dlr-1',
            'reference' => 'REF-DLR',
            'sender' => 'NEXTSMS',
            'recipient' => '255744123456',
            'text' => 'Track me',
            'status' => 'pending',
            'sent_at' => now(),
        ]);

        $this->postJson('/api/v1/sms/delivery-report', [
            'results' => [[
                'messageId' => 'msg-dlr-1',
                'to' => '255744123456',
                'status' => [
                    'groupName' => 'DELIVERED',
                    'name' => 'DELIVERED_TO_HANDSET',
                ],
                'doneAt' => now()->toISOString(),
            ]],
        ])->assertOk()
            ->assertJsonPath('data.received', 1);

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertSame(1, SmsDeliveryReport::query()->where('sms_message_id', $message->id)->count());
    }

    public function test_delivery_report_rejects_invalid_secret_when_configured(): void
    {
        config(['services.nextsms.callback_secret' => 'secret-123']);

        $this->postJson('/api/v1/sms/delivery-report', [
            'results' => [['messageId' => 'x', 'status' => 'DELIVERED']],
        ])->assertStatus(403);
    }

    public function test_superadmin_can_read_sms_balance(): void
    {
        Http::fake([
            'messaging-service.co.tz/*' => Http::response(['balance' => 1250.5, 'currency' => 'TZS'], 200),
        ]);

        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->withHeaders($this->authHeaders($superadmin))
            ->getJson('/api/v1/superadmin/sms/balance')
            ->assertOk()
            ->assertJsonPath('data.body.balance', 1250.5);
    }
}

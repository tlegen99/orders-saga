<?php

use App\Models\IdempotencyKey;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('удаление просроченного ключа в idempotency_keys', function () {
    DB::table('idempotency_keys')->insert([
        'key' => 'old-key-1',
        'request_hash' => '5bfd0ff8fa2effd5efb9ed868e28451a6a4c38fae876bd73392c0c7ebfd3b6d4',
        'created_at' => now()->subDays(2),
        'expires_at' => now()->subDays(),
    ]);
    DB::table('idempotency_keys')->insert([
        'key' => 'fresh-key-2',
        'request_hash' => '5bfd0ff8fa2effd5efb9ed868e28451a6a4c38fae876bd73392c0c7ebfd3b6d5',
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('model:prune');

    expect(IdempotencyKey::where('key', 'old-key-1')->count())->toBe(0)
        ->and(IdempotencyKey::where('key', 'fresh-key-2')->count())->toBe(1);
});

it('удаление просроченного письма в outbox_messages', function () {
    DB::table('outbox_messages')->insert([
        'id' => '01a1bfe2-e625-72e7-9341-c52d5b2adee5',
        'subject' => 'payments.charge',
        'type' => 'ChargePayment',
        'aggregate_id' => '75f23dbb-c3b7-4676-bf42-8d4a72dd94ee',
        'payload' => json_encode(['amount' => 450000, 'order_uuid' => '75f23dbb-c3b7-4676-bf42-8d4a72dd94ee']),
        'created_at' => now()->subDays(9),
        'published_at' => now()->subDays(8),
    ]);
    DB::table('outbox_messages')->insert([
        'id' => '01a1bfe2-e625-72e7-9341-c52d5b2adee6',
        'subject' => 'payments.charge',
        'type' => 'ChargePayment',
        'aggregate_id' => '75f23dbb-c3b7-4676-bf42-8d4a72dd94eq',
        'payload' => json_encode(['amount' => 450000, 'order_uuid' => '75f23dbb-c3b7-4676-bf42-8d4a72dd94eq']),
        'created_at' => now(),
        'published_at' => now()->addDay(),
    ]);

    $this->artisan('model:prune');

    expect(OutboxMessage::where('id', '01a1bfe2-e625-72e7-9341-c52d5b2adee5')->count())->toBe(0)
        ->and(OutboxMessage::where('id', '01a1bfe2-e625-72e7-9341-c52d5b2adee6')->count())->toBe(1);
});

it('удаление отметок processed_messages старше 30 дней', function () {
    $oldId = (string) Str::uuid();
    $freshId = (string) Str::uuid();

    DB::table('processed_messages')->insert([
        ['consumer' => 'payments', 'message_id' => $oldId, 'processed_at' => now()->subDays(31)],
        ['consumer' => 'payments', 'message_id' => $freshId, 'processed_at' => now()->subDays(29)],
    ]);

    $this->artisan('model:prune');

    expect(DB::table('processed_messages')->where('message_id', $oldId)->exists())->toBeFalse()
        ->and(DB::table('processed_messages')->where('message_id', $freshId)->exists())->toBeTrue();
});

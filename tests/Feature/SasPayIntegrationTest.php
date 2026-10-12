<?php

declare(strict_types=1);

use App\Models\SubscriptionPayment;
use App\Models\Team;
use App\Models\User;
use App\Services\SasPayService;
use App\Services\SubscriptionQuotaService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Nafiswatsiq\Subbase\Models\Plan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Config::set('saspay.api_key', 'sk_test_fake_api_key_123456');
    Config::set('saspay.webhook_secret', 'fake_signing_secret_abcdef');
});

test('it creates a saspay checkout session with exact decimal amount and stores pending payment', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();

    Http::fake([
        'https://api.saspay.me/api/v1/checkout-sessions/' => Http::response([
            'id' => 'sess_123456',
            'slug' => 'slug_123456',
            'checkout_url' => 'https://pay.saspay.me/checkout/slug_123456',
            'amount' => '800.00',
            'currency' => 'XOF',
            'status' => 'PENDING',
        ], 201),
    ]);

    $sasPayService = app(SasPayService::class);
    $result = $sasPayService->createCheckoutSession($user, $proPlan, 'monthly', 'XOF');

    expect($result['success'])->toBeTrue()
        ->and($result['checkout_url'])->toBe('https://pay.saspay.me/checkout/slug_123456')
        ->and($result['payment'])->toBeInstanceOf(SubscriptionPayment::class)
        ->and($result['payment']->status)->toBe('PENDING')
        ->and($result['payment']->amount)->toBe('800.00')
        ->and($result['payment']->currency)->toBe('XOF');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['amount'] === '800.00'
            && $body['currency'] === 'XOF'
            && isset($body['customer_email']);
    });

});

test('it rejects saspay webhook when timestamp is older than tolerance limit', function () {
    $secret = 'fake_signing_secret_abcdef';
    $oldTimestamp = (string) (time() - 360); // 6 minutes ago (exceeds 300s)
    $payload = json_encode(['event' => 'transaction.success', 'data' => []]);

    $signature = hash_hmac('sha256', "{$oldTimestamp}.{$payload}", $secret);

    $response = $this->postJson('/api/webhooks/saspay', json_decode($payload, true), [
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => $oldTimestamp,
        'X-Webhook-Event' => 'transaction.success',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['success' => false]);
});

test('it rejects saspay webhook when signature does not match', function () {
    $currentTimestamp = (string) time();
    $payload = json_encode(['event' => 'transaction.success', 'data' => []]);

    $response = $this->postJson('/api/webhooks/saspay', json_decode($payload, true), [
        'X-Webhook-Signature' => 'invalid_fake_signature_hash',
        'X-Webhook-Timestamp' => $currentTimestamp,
        'X-Webhook-Event' => 'transaction.success',
    ]);

    $response->assertStatus(400)
        ->assertJsonFragment(['success' => false]);
});

test('it handles webhook test event successfully', function () {
    $secret = 'fake_signing_secret_abcdef';
    $timestamp = (string) time();
    $payload = json_encode(['event' => 'webhook.test', 'data' => []]);
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    $response = $this->call('POST', '/api/webhooks/saspay', [], [], [], [
        'HTTP_X-Webhook-Signature' => $signature,
        'HTTP_X-Webhook-Timestamp' => $timestamp,
        'HTTP_X-Webhook-Event' => 'webhook.test',
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(200)
        ->assertJsonFragment(['success' => true]);
});

test('it activates pro subscription on verified transaction.success webhook', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();

    $payment = SubscriptionPayment::create([
        'user_id' => $user->id,
        'plan_id' => $proPlan->id,
        'period' => 'monthly',
        'amount' => '800.00',
        'currency' => 'XOF',
        'status' => 'PENDING',
        'payment_id' => 'tx_test_9999',
    ]);

    $secret = 'fake_signing_secret_abcdef';
    $timestamp = (string) time();
    $payloadData = [
        'event' => 'transaction.success',
        'data' => [
            'id' => 'tx_test_9999',
            'reference' => 'TXN-2026-TEST',
            'amount' => '800.00',
            'net_amount' => '780.00',
            'charged' => '800.00',
            'currency' => 'XOF',
            'status' => 'SUCCESS',
            'metadata' => [
                'payment_record_id' => $payment->id,
                'user_id' => $user->id,
                'plan_id' => $proPlan->id,
                'period' => 'monthly',
            ],
        ],
    ];

    $rawPayload = json_encode($payloadData);
    $signature = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);

    $response = $this->call('POST', '/api/webhooks/saspay', [], [], [], [
        'HTTP_X-Webhook-Signature' => $signature,
        'HTTP_X-Webhook-Timestamp' => $timestamp,
        'HTTP_X-Webhook-Event' => 'transaction.success',
        'CONTENT_TYPE' => 'application/json',
    ], $rawPayload);

    $response->assertStatus(200);

    $payment->refresh();
    expect($payment->status)->toBe('SUCCESS')
        ->and($payment->reference)->toBe('TXN-2026-TEST')
        ->and($payment->paid_at)->not->toBeNull();

    // Vérifier que l'utilisateur est maintenant Pro et a accès au cloud
    $quotaService = app(SubscriptionQuotaService::class);
    expect($quotaService->getCurrentPlan($user)->slug)->toBe('pro')
        ->and($quotaService->canUseCloudBackup($user))->toBeTrue()
        ->and($quotaService->getLinksLimit($user))->toBe('unlimited');
});

test('it downgrades expired subscriptions to free plan via command', function () {
    $user = User::factory()->create();
    $proPlan = Plan::where('slug', 'pro')->first();
    $quotaService = app(SubscriptionQuotaService::class);

    // Initialiser l'abonnement pro
    $subscription = $quotaService->subscribeToPlan($user, $proPlan);
    expect($subscription->plan->slug)->toBe('pro');

    // Mettre à échéance passée
    $subscription->update([
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscriptions:check-expirations')
        ->assertExitCode(0);

    $user->unsetRelation('planSubscriptions');
    expect($quotaService->getCurrentPlan($user)->slug)->toBe('free');
});

test('it blocks team invitation when member limit is reached on free plan', function () {
    $user = User::factory()->create();
    $team = Team::create([
        'user_id' => $user->id,
        'name' => 'Solo Team',
        'slug' => 'solo-team',
        'is_personal' => true,
    ]);

    $quotaService = app(SubscriptionQuotaService::class);
    // Le plan gratuit n'autorise que 1 membre (le propriétaire)
    expect($quotaService->canAddTeamMember($team, $user))->toBeFalse();

    // Passer au plan Pro (autorise jusqu'à 3 membres)
    $quotaService->subscribeToPlan($user, 'pro');
    expect($quotaService->canAddTeamMember($team, $user))->toBeTrue();
});

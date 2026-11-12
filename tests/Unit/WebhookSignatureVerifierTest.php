<?php

declare(strict_types=1);

namespace SegmentHoldings\FilamentBillingProviders\Tests\Unit;

use SegmentHoldings\FilamentBillingProviders\Tests\TestCase;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\LemonSqueezySignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PaddleSignatureVerifier;
use SegmentHoldings\FilamentBillingProviders\Webhooks\Verifiers\PolarSignatureVerifier;

/**
 * Verifies HMAC algorithms against labeled FAKE fixtures (not live vendor payloads).
 */
final class WebhookSignatureVerifierTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = dirname(__DIR__).'/Fixtures/webhooks';
    }

    public function test_lemon_squeezy_accepts_known_hmac_hex_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/lemon_squeezy_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/lemon_squeezy_meta.json'), true);

        $verifier = new LemonSqueezySignatureVerifier;

        $this->assertTrue($verifier->verify($body, [
            'X-Signature' => $meta['signature'],
        ], $meta['secret']));
    }

    public function test_lemon_squeezy_rejects_tampered_body(): void
    {
        $body = file_get_contents($this->fixtures.'/lemon_squeezy_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/lemon_squeezy_meta.json'), true);

        $verifier = new LemonSqueezySignatureVerifier;

        $this->assertFalse($verifier->verify($body.' ', [
            'X-Signature' => $meta['signature'],
        ], $meta['secret']));
    }

    public function test_paddle_accepts_known_ts_body_hmac_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/paddle_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/paddle_meta.json'), true);

        $verifier = new PaddleSignatureVerifier(
            toleranceSeconds: 10_000_000_000,
            now: (int) $meta['timestamp'],
        );

        $this->assertTrue($verifier->verify($body, [
            'Paddle-Signature' => $meta['header_value'],
        ], $meta['secret']));
    }

    public function test_paddle_rejects_wrong_secret(): void
    {
        $body = file_get_contents($this->fixtures.'/paddle_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/paddle_meta.json'), true);

        $verifier = new PaddleSignatureVerifier(
            toleranceSeconds: 10_000_000_000,
            now: (int) $meta['timestamp'],
        );

        $this->assertFalse($verifier->verify($body, [
            'Paddle-Signature' => $meta['header_value'],
        ], 'wrong-secret'));
    }

    public function test_paddle_rejects_stale_timestamp(): void
    {
        $body = file_get_contents($this->fixtures.'/paddle_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/paddle_meta.json'), true);

        $verifier = new PaddleSignatureVerifier(
            toleranceSeconds: 5,
            now: (int) $meta['timestamp'] + 60,
        );

        $this->assertFalse($verifier->verify($body, [
            'Paddle-Signature' => $meta['header_value'],
        ], $meta['secret']));
    }

    public function test_polar_accepts_legacy_key_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/polar_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/polar_meta.json'), true);

        $verifier = new PolarSignatureVerifier(
            toleranceSeconds: 10_000_000_000,
            now: (int) $meta['webhook_timestamp'],
        );

        $this->assertTrue($verifier->verify($body, [
            'webhook-id' => $meta['webhook_id'],
            'webhook-timestamp' => $meta['webhook_timestamp'],
            'webhook-signature' => $meta['header_legacy'],
        ], $meta['secret']));
    }

    public function test_polar_accepts_standard_webhooks_key_fixture(): void
    {
        $body = file_get_contents($this->fixtures.'/polar_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/polar_meta.json'), true);

        $verifier = new PolarSignatureVerifier(
            toleranceSeconds: 10_000_000_000,
            now: (int) $meta['webhook_timestamp'],
        );

        $this->assertTrue($verifier->verify($body, [
            'webhook-id' => $meta['webhook_id'],
            'webhook-timestamp' => $meta['webhook_timestamp'],
            'webhook-signature' => $meta['header_standard'],
        ], $meta['secret']));
    }

    public function test_polar_rejects_missing_headers(): void
    {
        $verifier = new PolarSignatureVerifier(now: 1710000000);

        $this->assertFalse($verifier->verify('{}', [], 'whsec_test'));
    }

    public function test_polar_rejects_timestamp_outside_tolerance(): void
    {
        $body = file_get_contents($this->fixtures.'/polar_body.json');
        $meta = json_decode(file_get_contents($this->fixtures.'/polar_meta.json'), true);

        $verifier = new PolarSignatureVerifier(
            toleranceSeconds: 300,
            now: (int) $meta['webhook_timestamp'] + 301,
        );

        $this->assertFalse($verifier->verify($body, [
            'webhook-id' => $meta['webhook_id'],
            'webhook-timestamp' => $meta['webhook_timestamp'],
            'webhook-signature' => $meta['header_legacy'],
        ], $meta['secret']));
    }

    public function test_short_signature_does_not_throw(): void
    {
        $verifier = new LemonSqueezySignatureVerifier;

        $this->assertFalse($verifier->verify('{"_fixture":"FAKE"}', [
            'X-Signature' => 'deadbeef',
        ], 'fake_ls_test_secret_1'));
    }
}

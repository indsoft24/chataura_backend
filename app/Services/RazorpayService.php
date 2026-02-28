<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    private string $keyId;
    private string $keySecret;
    private string $baseUrl = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id', '');
        $this->keySecret = config('services.razorpay.key_secret', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->keyId) && !empty($this->keySecret);
    }

    /**
     * Create order. Amount in paise (INR * 100).
     *
     * @return array{id: string, amount: int}|null
     */
    public function createOrder(int $amountPaise, string $currency = 'INR'): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        try {
            $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->post("{$this->baseUrl}/orders", [
                    'amount' => $amountPaise,
                    'currency' => $currency,
                ]);
            if (!$response->successful()) {
                Log::warning('Razorpay create order failed', ['body' => $response->body()]);
                return null;
            }
            $data = $response->json();
            return [
                'id' => $data['id'] ?? '',
                'amount' => (int) ($data['amount'] ?? $amountPaise),
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay create order exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify payment signature (HMAC SHA256).
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (empty($this->keySecret)) {
            return false;
        }
        $payload = $orderId . '|' . $paymentId;
        $expected = hash_hmac('sha256', $payload, $this->keySecret);
        return hash_equals($expected, $signature);
    }
}

<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MidtransClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSnapTransaction(array $payload): array
    {
        $response = $this->client()
            ->post($this->snapBaseUrl().'/snap/v1/transactions', $payload);

        $this->ensureSuccessful($response, 'Gagal membuat transaksi Snap Midtrans.');

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $orderId): array
    {
        $response = $this->client()
            ->get($this->apiBaseUrl().'/v2/'.rawurlencode($orderId).'/status');

        // Snap Get Status dapat mengembalikan 404 jika customer belum memilih
        // metode pembayaran. Kondisi ini bukan kegagalan aplikasi.
        if ($response->status() === 404) {
            return [
                'status_code' => '404',
                'transaction_status' => 'not_found',
                'status_message' => 'Transaksi belum terbentuk di Midtrans.',
            ];
        }

        $this->ensureSuccessful($response, 'Gagal mengambil status transaksi Midtrans.');

        return $response->json();
    }

    private function client(): PendingRequest
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '') {
            throw new PaymentGatewayException(
                'MIDTRANS_SERVER_KEY belum dikonfigurasi pada file .env.',
            );
        }

        return Http::asJson()
            ->acceptJson()
            ->withBasicAuth($serverKey, '')
            ->timeout((int) config('midtrans.timeout', 30));
    }

    private function snapBaseUrl(): string
    {
        return (bool) config('midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    private function apiBaseUrl(): string
    {
        return (bool) config('midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function ensureSuccessful(Response $response, string $fallbackMessage): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error_messages.0')
            ?? $response->json('status_message')
            ?? $fallbackMessage;

        throw new PaymentGatewayException(
            sprintf('%s HTTP %d: %s', $fallbackMessage, $response->status(), $message),
        );
    }
}

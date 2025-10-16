<?php

namespace App\Services\SmsProviders;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AqilasProvider implements SmsProviderInterface
{
    protected string $apiUrl = 'https://www.aqilas.com/api/v1/sms';
    protected ?string $apiToken;
    protected string $defaultFrom;

    public function __construct()
    {
        $this->apiToken = config('services.aqilas.token');
        $this->defaultFrom = config('services.aqilas.default_from', 'SAWES');
    }

    /**
     * Send SMS via Aqilas
     */
    public function sendSms(array $params): array
    {
        $client = new Client();

        // Use default 'from' if not provided
        $from = $params['from'] ?? $this->defaultFrom;

         $payload = [
             'from' => $from,
             'text' => $params['message'],
             'to' => [$this->formatPhoneNumber($params['to'])], // Array format required by Aqilas API
         ];

        if (!empty($params['send_at'])) {
            $payload['send_at'] = $params['send_at'];
        }

        try {
            $response = $client->post($this->apiUrl, [
                'headers' => [
                    'X-AUTH-TOKEN' => $this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'verify' => false, // Temporarily disable SSL verification for testing
            ]);

            $data = json_decode($response->getBody(), true);

            Log::info('Aqilas SMS sent successfully', [
                'to' => $params['to'],
                'response' => $data,
            ]);

            return [
                'success' => true,
                'message_id' => $data['id'] ?? $data['message_id'] ?? null,
                'status' => $data['status'] ?? 'sent',
                'data' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Aqilas SMS sending failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Aqilas SMS sending failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SMS delivery status
     */
    public function getStatus(string $messageId): array
    {
        try {
            $client = new Client();
            $response = $client->get($this->apiUrl . '/sms/status/' . $messageId, [
                'headers' => [
                    'X-AUTH-TOKEN' => $this->apiToken,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }

            return [
                'success' => false,
                'error' => 'Failed to get status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get account balance
     */
    public function getBalance(): float
    {
        try {
            $client = new Client();
            $response = $client->get($this->apiUrl . '/account/balance', [
                'headers' => [
                    'X-AUTH-TOKEN' => $this->apiToken,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                return (float) ($data['balance'] ?? 0);
            }

            return 0.0;
        } catch (\Exception $e) {
            Log::error('Failed to get Aqilas balance', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Format phone number for Burkina Faso
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // Ensure it starts with country code
        if (!str_starts_with($phone, '226')) {
            $phone = '226' . $phone;
        }

        return '+' . $phone;
    }
}

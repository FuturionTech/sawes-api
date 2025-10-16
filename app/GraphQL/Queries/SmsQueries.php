<?php

namespace App\GraphQL\Queries;

use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SmsQueries
{
    /**
     * Get SMS provider information
     */
    public function providerInfo($rootValue, array $args): array
    {
        $smsService = app(SmsService::class);

        return [
            'current_provider' => $smsService->getProviderName(), // Return lowercase for enum matching
            'available_providers' => ['aqilas', 'twilio'], // Return lowercase for enum matching
            'aqilas_configured' => !empty(config('services.aqilas.token')),
            'twilio_configured' => !empty(config('services.twilio.account_sid')),
        ];
    }

    /**
     * Get SMS account balance
     */
    public function balance($rootValue, array $args): array
    {
        $smsService = app(SmsService::class);

        // Switch provider if specified
        if (isset($args['provider'])) {
            $smsService->switchProvider(strtolower($args['provider']));
        }

        try {
            $balance = $smsService->getBalance();

            return [
                'success' => true,
                'provider' => $smsService->getProviderName(),
                'balance' => $balance,
                'currency' => $smsService->getProviderName() === 'aqilas' ? 'XOF' : 'USD',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get SMS balance', [
                'error' => $e->getMessage(),
                'provider' => $smsService->getProviderName(),
            ]);

            return [
                'success' => false,
                'provider' => $smsService->getProviderName(),
                'balance' => 0.0,
                'currency' => null,
            ];
        }
    }

    /**
     * Get SMS delivery status
     */
    public function status($rootValue, array $args): array
    {
        $smsService = app(SmsService::class);

        // Switch provider if specified
        if (isset($args['provider'])) {
            $smsService->switchProvider(strtolower($args['provider']));
        }

        try {
            $result = $smsService->getStatus($args['message_id']);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message_id' => $args['message_id'],
                    'provider' => $smsService->getProviderName(),
                    'status' => $result['status'] ?? 'unknown',
                    'error_code' => $result['error_code'] ?? null,
                    'error_message' => $result['error_message'] ?? null,
                    'date_sent' => $result['data']['date_sent'] ?? null,
                    'to' => $result['data']['to'] ?? null,
                    'from' => $result['data']['from'] ?? null,
                    'price' => $result['data']['price'] ?? null,
                    'price_unit' => $result['data']['price_unit'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message_id' => $args['message_id'],
                'provider' => $smsService->getProviderName(),
                'status' => 'error',
                'error_message' => $result['error'] ?? 'Failed to get status',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get SMS status', [
                'error' => $e->getMessage(),
                'message_id' => $args['message_id'],
                'provider' => $smsService->getProviderName(),
            ]);

            return [
                'success' => false,
                'message_id' => $args['message_id'],
                'provider' => $smsService->getProviderName(),
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ];
        }
    }
}

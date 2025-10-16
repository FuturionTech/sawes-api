<?php

namespace App\GraphQL\Mutations;

use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class SmsMutations
{
    /**
     * Send SMS message
     */
    public function send($rootValue, array $args): array
    {
        $input = $args['input'];
        $smsService = app(SmsService::class);

        // Switch provider if specified
        if (isset($input['provider'])) {
            $smsService->switchProvider(strtolower($input['provider']));
        }

        try {
            // Prepare SMS params
            $params = [
                'to' => $input['to'],
                'message' => $input['message'],
            ];

            if (isset($input['from'])) {
                $params['from'] = $input['from'];
            }

            if (isset($input['send_at'])) {
                $params['send_at'] = $input['send_at'];
            }

            // Send SMS
            $result = $smsService->sendWithParams($params);

            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'provider' => $smsService->getProviderName(),
                    'message_id' => $result['message_id'] ?? null,
                    'status' => $result['status'] ?? 'sent',
                ];

                // Add Aqilas-specific fields if available
                if (isset($result['data'])) {
                    $data = $result['data'];
                    if (isset($data['bulk_id'])) {
                        $response['bulk_id'] = $data['bulk_id'];
                    }
                    if (isset($data['cost'])) {
                        $response['cost'] = (float) $data['cost'];
                    }
                    if (isset($data['currency'])) {
                        $response['currency'] = $data['currency'];
                    }
                }

                Log::info('SMS sent successfully via GraphQL', [
                    'to' => $input['to'],
                    'provider' => $smsService->getProviderName(),
                    'message_id' => $result['message_id'] ?? null,
                ]);

                return $response;
            }

            // SMS sending failed
            Log::error('SMS sending failed via GraphQL', [
                'to' => $input['to'],
                'provider' => $smsService->getProviderName(),
                'error' => $result['error'] ?? 'Unknown error',
                'message' => $result['message'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to send SMS',
                'provider' => $smsService->getProviderName(),
                'message_id' => null,
                'status' => 'failed',
            ];

        } catch (\Exception $e) {
            Log::error('SMS sending exception via GraphQL', [
                'to' => $input['to'],
                'provider' => $smsService->getProviderName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS sending failed: ' . $e->getMessage(),
                'provider' => $smsService->getProviderName(),
                'message_id' => null,
                'status' => 'error',
            ];
        }
    }
}

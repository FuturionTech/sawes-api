<?php

namespace App\Services\SmsProviders;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class TwilioProvider implements SmsProviderInterface
{
    protected ?TwilioClient $client;
    protected ?string $fromNumber;
    protected ?string $accountSid;
    protected ?string $authToken;

    public function __construct()
    {
        $this->accountSid = config('services.twilio.account_sid');
        $this->authToken = config('services.twilio.auth_token');
        $this->fromNumber = config('services.twilio.from_number');

        if ($this->accountSid && $this->authToken) {
            $this->client = new TwilioClient($this->accountSid, $this->authToken);
        }
    }

    /**
     * Send SMS via Twilio
     */
    public function sendSms(array $params): array
    {
        if (!$this->client) {
            return [
                'success' => false,
                'error' => 'Twilio client not configured',
                'message' => 'Twilio credentials are missing',
            ];
        }

        try {
            $from = $params['from'] ?? $this->fromNumber;
            $to = $this->formatPhoneNumber($params['to']);

            $message = $this->client->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $params['message'],
                ]
            );

            Log::info('Twilio SMS sent successfully', [
                'to' => $to,
                'message_sid' => $message->sid,
                'status' => $message->status,
            ]);

            return [
                'success' => true,
                'message_id' => $message->sid,
                'status' => $message->status,
                'data' => [
                    'sid' => $message->sid,
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                    'price' => $message->price,
                    'price_unit' => $message->priceUnit,
                ],
            ];

        } catch (TwilioException $e) {
            Log::error('Twilio SMS sending failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => 'Twilio SMS sending failed',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS sending failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Twilio SMS sending failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SMS delivery status
     */
    public function getStatus(string $messageId): array
    {
        if (!$this->client) {
            return [
                'success' => false,
                'error' => 'Twilio client not configured',
            ];
        }

        try {
            $message = $this->client->messages($messageId)->fetch();

            return [
                'success' => true,
                'message_id' => $message->sid,
                'status' => $message->status,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'data' => [
                    'sid' => $message->sid,
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                    'date_created' => $message->dateCreated?->format('Y-m-d H:i:s'),
                    'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                    'price' => $message->price,
                    'price_unit' => $message->priceUnit,
                ],
            ];

        } catch (TwilioException $e) {
            Log::error('Failed to get Twilio message status', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
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
        if (!$this->client) {
            return 0.0;
        }

        try {
            $balance = $this->client->balance->fetch();
            return (float) $balance->balance;

        } catch (\Exception $e) {
            Log::error('Failed to get Twilio balance', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Format phone number for E.164 format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // If it doesn't start with +, add it
        if (!str_starts_with($phone, '+')) {
            // If it doesn't have a country code, assume Burkina Faso (+226)
            if (!str_starts_with($phone, '226') && strlen($phone) === 8) {
                $phone = '226' . $phone;
            }
            $phone = '+' . $phone;
        }

        return $phone;
    }
}

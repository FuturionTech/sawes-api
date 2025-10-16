<?php

namespace App\Services;

use App\Services\SmsProviders\AqilasProvider;
use App\Services\SmsProviders\TwilioProvider;
use App\Services\SmsProviders\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected SmsProviderInterface $provider;
    protected string $providerName;

    public function __construct()
    {
        $this->providerName = config('services.sms_provider', env('SMS_PROVIDER', 'aqilas'));
        $this->provider = $this->resolveProvider();
    }

    /**
     * Resolve the SMS provider based on configuration
     */
    private function resolveProvider(): SmsProviderInterface
    {
        return match ($this->providerName) {
            'twilio' => new TwilioProvider(),
            'aqilas' => new AqilasProvider(),
            default => new AqilasProvider(),
        };
    }

    /**
     * Send SMS message
     */
    public function send(string $to, string $message, ?string $from = null): array
    {
        Log::info("Sending SMS via {$this->providerName}", [
            'to' => $to,
            'message_length' => strlen($message),
        ]);

        $params = [
            'to' => $to,
            'message' => $message,
        ];

        if ($from) {
            $params['from'] = $from;
        }

        return $this->provider->sendSms($params);
    }

    /**
     * Send SMS with additional parameters
     */
    public function sendWithParams(array $params): array
    {
        Log::info("Sending SMS via {$this->providerName}", [
            'params' => array_diff_key($params, ['message' => null]), // Don't log message content
        ]);

        return $this->provider->sendSms($params);
    }

    /**
     * Get SMS delivery status
     */
    public function getStatus(string $messageId): array
    {
        return $this->provider->getStatus($messageId);
    }

    /**
     * Get account balance
     */
    public function getBalance(): float
    {
        return $this->provider->getBalance();
    }

    /**
     * Get current provider name
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * Switch to a different provider
     */
    public function switchProvider(string $providerName): self
    {
        $this->providerName = $providerName;
        $this->provider = $this->resolveProvider();
        return $this;
    }
}

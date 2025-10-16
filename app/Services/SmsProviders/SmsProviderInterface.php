<?php

namespace App\Services\SmsProviders;

interface SmsProviderInterface
{
    /**
     * Send SMS message
     *
     * @param array $params
     * @return array
     */
    public function sendSms(array $params): array;

    /**
     * Get SMS delivery status
     *
     * @param string $messageId
     * @return array
     */
    public function getStatus(string $messageId): array;

    /**
     * Get available balance
     *
     * @return float
     */
    public function getBalance(): float;
}

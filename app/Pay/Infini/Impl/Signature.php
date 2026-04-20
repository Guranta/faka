<?php
declare(strict_types=1);

namespace App\Pay\Infini\Impl;

use App\Pay\Signature as SignatureInterface;

class Signature implements SignatureInterface
{
    public function verification(array $data, array $config): bool
    {
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
        $eventId = $_SERVER['HTTP_X_WEBHOOK_EVENT_ID'] ?? '';
        $receivedSig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

        if ($timestamp === '' || $eventId === '' || $receivedSig === '') {
            return false;
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        $rawBody = file_get_contents('php://input');
        $signedContent = $timestamp . '.' . $eventId . '.' . $rawBody;
        $expectedSig = hash_hmac('sha256', $signedContent, $config['secret_key']);

        return hash_equals($expectedSig, $receivedSig);
    }
}

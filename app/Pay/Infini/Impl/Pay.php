<?php
declare(strict_types=1);

namespace App\Pay\Infini\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Pay\Pay as PayInterface;

class Pay extends Base implements PayInterface
{
    private const SANDBOX_URL = 'https://openapi-sandbox.infini.money';
    private const PRODUCTION_URL = 'https://openapi.infini.money';

    private function getBaseUrl(): string
    {
        return ($this->config['env'] ?? 'sandbox') === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
    }

    private function signRequest(string $method, string $path, ?string $body = null): array
    {
        $gmtTime = gmdate('D, d M Y H:i:s') . ' GMT';
        $signingString = $this->config['key_id'] . "\n" . $method . ' ' . $path . "\ndate: " . $gmtTime . "\n";
        $signature = base64_encode(hash_hmac('sha256', $signingString, $this->config['secret_key'], true));

        $headers = [
            'Date' => $gmtTime,
            'Authorization' => 'Signature keyId="' . $this->config['key_id']
                . '",algorithm="hmac-sha256",headers="@request-target date",signature="' . $signature . '"',
        ];

        if ($body !== null) {
            $headers['Digest'] = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    private function apiRequest(string $method, string $path, ?array $jsonBody = null): array
    {
        $body = $jsonBody !== null
            ? json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $headers = $this->signRequest($method, $path, $body);

        $this->log("{$method} {$path}" . ($body ? " body={$body}" : ''));

        $response = $this->http()->request($method, $this->getBaseUrl() . $path, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        $this->log("Response: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    public function trade(): PayEntity
    {
        $requestId = $this->generateRequestId();
        $path = '/v1/acquiring/order';

        $payMethods = array_map('intval', explode(',', $this->config['pay_methods'] ?? '1'));
        $orderBody = array_filter([
            'amount' => $this->amount,
            'request_id' => $requestId,
            'client_reference' => $this->tradeNo,
            'order_desc' => 'Order ' . $this->tradeNo,
            'success_url' => $this->returnUrl,
            'failure_url' => $this->returnUrl,
            'pay_methods' => $payMethods,
        ]);

        $expire = (int)($this->config['order_expire'] ?? 0);
        if ($expire > 0) {
            $orderBody['expires_in'] = $expire;
        }

        $this->log("Creating Infini order for trade_no={$this->tradeNo}, amount={$this->amount}");

        $result = $this->apiRequest('POST', $path, $orderBody);

        $entity = new PayEntity();
        $entity->setType(PayInterface::TYPE_REDIRECT);
        $entity->setUrl($result['checkout_url']);

        $this->log("checkout_url={$result['checkout_url']}, order_id={$result['order_id']}");

        return $entity;
    }

    private function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

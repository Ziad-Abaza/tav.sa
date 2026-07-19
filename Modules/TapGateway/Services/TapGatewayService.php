<?php

namespace Modules\TapGateway\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TapGatewayService
{
    private string $secretKey;

    private bool $sandboxMode;

    private string $baseUrl;

    private Client $client;

    public function __construct(string $secretKey, bool $sandboxMode = true)
    {
        $this->secretKey = $secretKey;
        $this->sandboxMode = $sandboxMode;
        $this->baseUrl = $sandboxMode
            ? 'https://api.tap.company/v2/'
            : 'https://api.tap.company/v2/';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Test the connection to Tap API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('tokens');

            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Create a charge
     */
    public function createCharge(array $data): array
    {
        try {
            $response = $this->client->post('charges', [
                'json' => $data,
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve a charge
     */
    public function retrieveCharge(string $chargeId): array
    {
        try {
            $response = $this->client->get("charges/{$chargeId}");

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture a charge
     */
    public function captureCharge(string $chargeId, array $data = []): array
    {
        try {
            $response = $this->client->post("charges/{$chargeId}/capture", [
                'json' => $data,
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refund a charge
     */
    public function refundCharge(string $chargeId, array $data): array
    {
        try {
            $response = $this->client->post("charges/{$chargeId}/refund", [
                'json' => $data,
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get API base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Check if in sandbox mode
     */
    public function isSandboxMode(): bool
    {
        return $this->sandboxMode;
    }
}

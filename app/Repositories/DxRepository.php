<?php


namespace App\Repositories;


use GuzzleHttp\ClientInterface;
use JsonException;

class DxRepository implements DxRepositoryInterface
{
    private string $url;
    private ClientInterface $client;

    public function __construct(ClientInterface $client, array $param)
    {
        $this->url = $param['url'];
        $this->client = $client;
    }

    public function get(string $dxCode, ?string $sfId = null): array
    {
        $response = $this->client->request('GET', $this->url . $dxCode . ($sfId !== null ? "/{$sfId}" : ''));
        try {
            $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $result = [];
        }
        return $result;
    }

    public function schedule(): array
    {
        $response = $this->client->request('GET', $this->url . 'schedule');
        try {
            $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $result = [];
        }
        return $result;
    }
}

<?php

namespace App\Feeds\Vendors\JSP\Utils;

use App\Feeds\Downloader\HttpDownloader;
use App\Feeds\Utils\ParserCrawler;

class SkuCrawlerBuilder
{
    private array $links = [];

    private array $response = [];

    private array $data = [];

    public function __construct(protected HttpDownloader $downloader, string ...$links)
    {
        $this->links = $links;
    }

    public function addLinks(string ...$link): static
    {
        $this->links = [...$this->links, ...$link];

        return $this;
    }

    public function build(): array
    {
        return $this->fetchFromLinks()->mapResponse(
            fn(array $response) => $this->getCrawlerFromResponse($response)
        );
    }

    private function fetchFromLinks(): static
    {
        $this->response = $this->downloader->fetch($this->links, true);

        return $this;
    }

    private function mapResponse(callable $mapper): array
    {
        return array_map($mapper, $this->response);
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    private function getCrawlerFromResponse(array $responseData, bool $linkIncluded = false): ParserCrawler|array
    {
        if ($linkIncluded) {
            return [
                'crawler' => new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]),
                'link' => $responseData['link']['url']
            ];
        }
        return new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]);
    }
}
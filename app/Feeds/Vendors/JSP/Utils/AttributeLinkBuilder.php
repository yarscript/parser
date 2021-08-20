<?php

namespace App\Feeds\Vendors\JSP\Utils;

use App\Feeds\Utils\Contracts\SelectorUtilContract;
use App\Feeds\Utils\ParserCrawler;

abstract class AttributeLinkBuilder
{
    protected array $items = [];

    protected int $selectedKey;

    public function __construct(
        protected string $attributeKey,
        protected SelectorUtilContract $selector
    )
    {
//        $this->recognizeItems();
    }

    public function applySelector(ParserCrawler $crawler): static
    {
        $items = $crawler->getContent($this->selector->getSelector());

        if (is_array($items)) {
            $this->items = $items;
            $this->selectedKey = 0;
        }

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }

    public function decorateLinks(string ...$links): array
    {
        $decoratedLinks = [];
        foreach ($links as $link) {
            $decoratedLinks[] = $this->buildLink($link);
        }

        return $decoratedLinks;
    }

    private function buildLink(string $link): string
    {
        return $link . (strpos($link, '?') ? "&{$this->getAttributeKey()}={$this->getSelected()}" : "?{$this->getAttributeKey()}={$this->getSelected()}");
    }

    public function selectSize(int $key): static
    {
        if (isset($this->items[ $key ])) {
            $this->selectedKey = $key;
        }

        return $this;
    }

    public function getSelected(): string
    {
        return $this->items[ $this->selectedKey ];
    }

    public function setFirst(): static
    {
        $this->selectedKey = isset($this->items[ 0 ]) ? 0 : null;
        return $this;
    }

    public function setLast(): static
    {
        $this->selectedKey = array_key_last($this->items);
        return $this;
    }

    public function next(): ?string
    {
        if ($this->selectedKey + 1 < sizeof($this->items)) {
            return $this->items[ ++$this->selectedKey ];
        }
        return null;
    }

    public function prev(): ?string
    {
        if ($this->selectedKey > 0) {
            return $this->items[ --$this->selectedKey ];
        }
        return null;
    }

    public function existLinks(): bool
    {
        return (bool)$this->items;
    }

    public function generateFromUrl(string $link): array
    {
        $generated = [];
        $clone = (clone $this)->setFirst();
        while ($clone->next())
            $generated[] = $this->buildLink($link);
        return $generated;
    }
}
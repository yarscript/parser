<?php

namespace App\Feeds\Vendors\JSP\Utils;

use App\Feeds\Utils\Contracts\SelectorUtilContract;
use App\Feeds\Utils\ParserCrawler;

class ColorAttributeLinkBuilder extends AttributeLinkBuilder
{
    public function __construct(
        protected string $attributeKey,
        protected SelectorUtilContract $selector,
        private string $selectorAttr
    )
    {
        parent::__construct($attributeKey, $selector);
    }

    public function applySelector(ParserCrawler $crawler): static
    {
        $this->items = $crawler->getAttrs($this->selector->getSelector(), $this->selectorAttr);

        return $this;
    }
}
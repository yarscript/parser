<?php

namespace App\Feeds\Vendors\JEF;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;

class Parser extends HtmlParser
{
    public function isGroup(): bool
    {
        return $this->getHtml('div.sizes, div.colors, div.wl-property');
    }

    public function getMpn(): string
    {
        return $this->getAttr('input#sku', 'value');
    }

    public function getProduct(): string
    {
        return trim($this->getText('h1.name'));
    }

    public function getShortDescription(): array
    {
        return [$this->getText('p.brief-description')];
    }

    public function getImages(): array
    {
        return [$this->getAttr('div.media img', 'src')];
    }

    public function getDescription(): string
    {
        return $this->getText('div.long-description');
    }

    public function getCostToUs(): float
    {
        return (float)substr($this->getText('.your-price p.inline span'), 1);
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $baseUrl = $this->getUri();
        $items = [];

        $colors = $this->node->getAttrs('div.grid-cell ul.color-group li.color img', 'title');
        $sizes = $this->node->getContent('label.size-button span.text');
        $options = $this->node->getAttrs('#sku option:not([value=\'\'])', 'value');

        $colors = is_array($colors) ? $colors : [];
        $sizes = is_array($sizes) ? $sizes : [];
        $options = is_array($options) ? $options : [];


        if (sizeof($colors) > 0) {
            [$colorCrawlers, $colorSizeCrawlers] = [[], []];

            foreach ($colors as $color) {
                $link = "{$baseUrl}?color={$color}";

                $responseData = $this->getVendor()->getDownloader()->fetch([$link], true)[ 0 ];
                $colorCrawlers[ $link ] = new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]);
            }

            if (sizeof($sizes) > 0) {
                foreach ($colorCrawlers as $link => $crawler) {
                    $colorSizes = $crawler->getContent('label.size-button span.text');

                    foreach ($colorSizes as $size) {
                        $colorSizeLink = "{$link}&size={$size}";

                        $responseData = $this->getVendor()->getDownloader()->fetch([$colorSizeLink], true)[ 0 ];
                        $colorSizeCrawlers[ $colorSizeLink ] = new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]);
                    }
                }
                if (sizeof($options) > 0) {
                    $test = 1;
                } else {
                    $items = $colorSizeCrawlers;
                }
            } else {
                $items = $colorCrawlers;
            }
        }

        if (sizeof($options) > 0 && empty($colors)) {
            $optionCrawlers = [];
            foreach ($options as $sku) {
                $optionLink = "{$baseUrl}?sku={$sku}";

                $responseData = $this->getVendor()->getDownloader()->fetch([$optionLink], true)[ 0 ];
                $optionCrawlers[ $optionLink ] = new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]);
            }
            $items = $optionCrawlers;

            if (!empty($sizes)) {
                $tst = 2;
            }
        }

        if (sizeof($sizes) > 0 && empty($colors)) {
            $test2 = 1;
            $sizeCrawlers = [];
            foreach ($sizes as $size) {
                $sizeLink = "{$baseUrl}?size={$size}";

                $responseData = $this->getVendor()->getDownloader()->fetch([$sizeLink], true)[ 0 ];
                $sizeCrawlers[ $sizeLink ] =  new ParserCrawler($responseData[ 'data' ]->getData(), $responseData[ 'link' ][ 'url' ]);
            }
            $items = $sizeCrawlers;

            if (!empty($options)) {
                $test = 1;
            }
        }


        $child = [];

        foreach ($items as $link => $item) {
            $fi = clone $parent_fi;

            $fi->setMpn($item->getAttr('input#sku', 'value'));
            $fi->setProductCode("{$this->getVendor()->getPrefix()}{$fi->getMpn()}");
            $fi->setProduct($item->getText('h1.name'));
            $fi->setCostToUs((float)substr($item->getText('.your-price p.inline span'), 1));
            $fi->setRAvail(self::DEFAULT_AVAIL_NUMBER);
            $fi->setImages([$item->getAttr('div.media img', 'src')]);

            $fi->setFulldescr($item->getText('div.long-description'));

            $fi->setSupplierInternalId($link);

            $child[] = $fi;
        }

        $parent_fi->setChildProducts($child);
        return $child;
    }
}
<?php

namespace App\Feeds\Vendors\JSP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Selector;
use App\Feeds\Utils\SelectorCollection;
use App\Feeds\Vendors\JSP\Utils\ColorAttributeLinkBuilder;
use App\Feeds\Vendors\JSP\Utils\OptionAttributeLinkBuilder;
use App\Feeds\Vendors\JSP\Utils\SizeAttributeLinkBuilder;
use App\Feeds\Vendors\JSP\Utils\SkuCrawlerBuilder;

class Parser extends HtmlParser
{
    private SelectorCollection $selectorCollection;

    public function __construct($vendor)
    {
        $this->selectorCollection = new SelectorCollection();
        $this->selectorCollection
            ->addSelectors('group', 'div.sizes', 'div.colors', 'div.wl-property')
            ->addSelectors('mpn', 'input#sku')
            ->addSelectors('product', 'h1.name')
            ->addSelectors('shortDescription', 'p.brief-description')
            ->addSelectors('images', '')
            ->addSelectors('description', 'div.long-description')
            ->addSelectors('costToUs', '.your-price p.inline span')
            ->addSelectors('brand', '')
            ->addSelectors('image', 'div.media img');

        parent::__construct($vendor);
    }

    public function isGroup(): bool
    {
        return $this->getHtml($this->selectorCollection->getSelector('group'));
    }

    public function getMpn(): string
    {
        return $this->getAttr($this->selectorCollection->getSelector('mpn'), 'value');
    }

    public function getProduct(): string
    {
        return trim($this->getText($this->selectorCollection->getSelector('product')));
    }

    public function getShortDescription(): array
    {
        return [$this->getText($this->selectorCollection->getSelector('shortDescription'))];
    }

    public function getImages(): array
    {
        return [$this->getAttr($this->selectorCollection->getSelector('image'), 'src')];
    }

    public function getDescription(): string
    {
        return $this->getText($this->selectorCollection->getSelector('description'));
    }

    public function getCostToUs(): float
    {
        return (float)substr($this->getText($this->selectorCollection->getSelector('costToUs')), 1);
    }

    public function getBrand(): ?string
    {
        return $this->short_product_info[ 'offers' ][ 0 ][ 'seller' ][ 'name' ] ?? '';
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    private function getColors(): ?array
    {
        $colors = (array)$this->getAttrs('div.grid-cell ul.color-group li.color img', 'title');
        return sizeof($colors) > 0 ? $colors : null;
    }

    private function getSizes(): ?array
    {
        $sizes = $this->getContent('.size-button');
        return is_array($sizes) ? $sizes : null;
    }

    private function getSelectOptions(): ?array
    {
        $options = (array)$this->getAttrs('select#sku option', 'value');
        return sizeof($options) > 0 ? $options : null;
    }


    public function getChildProducts(FeedItem $parent_fi): array
    {
        $baseUrl = $this->getUri();
        $items = [];

        $optionsSelector = new Selector('option', '#sku option:not([value=\'\'])');

        $optionsLinkBuilder = new OptionAttributeLinkBuilder('sku', $optionsSelector, 'value');
        $sizeLinkBuilder = new SizeAttributeLinkBuilder('size', new Selector('size-button', 'label.size-button span.text'));
        $colorLinksBuilder = new ColorAttributeLinkBuilder('color', new Selector('color-image', 'div.grid-cell ul.color-group li.color img'), 'title');

        $colors = $colorLinksBuilder->applySelector($this->node)->getItems();
        $sizes = $sizeLinkBuilder->applySelector($this->node)->getItems();
        $options = $optionsLinkBuilder->applySelector($this->node)->getItems();

        if (sizeof($colors) > 0) {
            [$colorCrawlers, $colorSizeCrawlers] = [[], []];

            foreach ($colors as $color) {
                $link = "{$baseUrl}?color={$color}";
                $colorCrawlers[ $link ] = (new SkuCrawlerBuilder($this->getVendor()
                                                                      ->getDownloader(), $link))->build()[ 0 ];
            }

            $test = 1;
            if (sizeof($sizes) > 0) {
                foreach ($colorCrawlers as $link => $crawler) {
                    $colorSizes = $sizeLinkBuilder->applySelector($crawler)->getItems();

                    foreach ($colorSizes as $size) {
                        $colorSizeLink = "{$link}&size={$size}";
                        $colorSizeCrawlers[ $colorSizeLink ] = (new SkuCrawlerBuilder($this->getVendor()
                                                                                           ->getDownloader(), $colorSizeLink))->build()[ 0 ];
                    }
                }
                if (sizeof($options) > 0) {
                    $test = 1;
                } else {
                    $items = $colorSizeCrawlers;
                }
            }
        }

        if (sizeof($options) > 0 && empty($colors)) {
            $test = 1;
            $optionCrawlers = [];
            foreach ($options as $sku) {
                $optionLink = "{$baseUrl}?sku={$sku}";
                $optionCrawlers[ $optionLink ] = (new SkuCrawlerBuilder($this->getVendor()
                                                                             ->getDownloader(), $optionLink))->build()[ 0 ];
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
                $sizeCrawlers[ $sizeLink ] = (new SkuCrawlerBuilder($this->getVendor()
                                                                         ->getDownloader(), $sizeLink))->build()[ 0 ];
            }
            $items = $sizeCrawlers;

            if (!empty($options)) {
                $test = 1;
            }
        }


        $child = [];
        $productCrawlers = [];

        $productCrawlers[] = $this->node;

        foreach ($items as $link => $item) {
            $fi = clone $parent_fi;

            $fi->setMpn($item->getAttr($this->selectorCollection->getSelector('mpn'), 'value'));
            $fi->setProductCode("{$this->getVendor()->getPrefix()}{$fi->getMpn()}");
            $fi->setProduct($item->getText($this->selectorCollection->getSelector('product')));
            $fi->setCostToUs((float)substr($item->getText($this->selectorCollection->getSelector('costToUs')), 1));
            $fi->setRAvail(self::DEFAULT_AVAIL_NUMBER);
            $fi->setImages([$item->getAttr($this->selectorCollection->getSelector('image'), 'src')]);

            $fi->setFulldescr($item->getText($this->selectorCollection->getSelector('description')));

            $fi->setSupplierInternalId($link);
//
//            $fi->setDimX( $product_data[ 'dimensions' ][ 'width' ] ?: null );
//            $fi->setDimY( $product_data[ 'dimensions' ][ 'height' ] ?: null );
//            $fi->setDimZ( $product_data[ 'dimensions' ][ 'length' ] ?: null );
//
//            $fi->setWeight( $product_data[ 'weight' ] ?: null );
            $child[] = $fi;
        }

        $parent_fi->setChildProducts($child);
        return $child;
    }
}
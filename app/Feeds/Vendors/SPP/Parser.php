<?php

namespace App\Feeds\Vendors\SPP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Helpers\StringHelper;
use Symfony\Component\DomCrawler\Crawler;

class Parser extends HtmlParser
{
    private array $short_product_info = [];

    public function beforeParse(): void
    {
        preg_match( '/application\/ld\+json">(.*)<\//', $this->node->html(), $matches );
        if ( isset( $matches[ 1 ] ) ) {
            $product_info = $matches[ 1 ];

            $this->short_product_info = json_decode( $product_info, true, 512, JSON_THROW_ON_ERROR );
        }
    }

    public function isGroup(): bool
    {
        return $this->getAttr( 'form.variations_form', 'data-product_variations' );
    }

    public function getProduct(): string
    {
        return $this->short_product_info[ 'name' ] ?? '';
    }

    public function getShortDescription(): array
    {
        $features = explode( '<br>', $this->getHtml( '.et_pb_wc_description_1_tb_body p' ) );
        return array_map( static fn( $feature ) => str_replace( [ "\n", '–' ], '', $feature ), array_filter( $features ) );
    }

    public function getImages(): array
    {
        return $this->node
            ->filter( '.woocommerce-product-gallery__wrapper a' )
            ->each( static fn( Crawler $crawler ) => $crawler->attr( 'href' ) );
    }

    public function getDescription(): string
    {
        $description = $this->getHtml( '.et_pb_wc_description_0_tb_body' );
        return StringHelper::removeSpaces( $description ) !== '<div class="et_pb_module_inner"> <span class="et_bloom_bottom_trigger"></span> </div>' ? $description : '';
    }

    public function getMpn(): string
    {
        return $this->short_product_info[ 'sku' ] ?? '';
    }

    public function getCostToUs(): float
    {
        if ( $this->isGroup() ) {
            return 0;
        }
        return $this->short_product_info[ 'offers' ][ 0 ][ 'priceSpecification' ][ 'price' ] ?? 0;
    }

    public function getBrand(): ?string
    {
        return $this->short_product_info[ 'offers' ][ 0 ][ 'seller' ][ 'name' ] ?? '';
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];

        $products = html_entity_decode( $this->getAttr( 'form.variations_form', 'data-product_variations' ) );
        $products_data = json_decode( $products, true, 512, JSON_THROW_ON_ERROR );

        foreach ( $products_data as $product_data ) {
            $fi = clone $parent_fi;

            $product_name = [];
            foreach ( $product_data[ 'attributes' ] as $attribute ) {
                $product_name[] = ucfirst( $attribute );
            }

            $fi->setMpn( $product_data[ 'sku' ] );
            $fi->setProduct( implode( ' ', $product_name ) );
            $fi->setCostToUs( StringHelper::getMoney( $product_data[ 'display_price' ] ) );
            $fi->setRAvail( $product_data[ 'is_in_stock' ] ? self::DEFAULT_AVAIL_NUMBER : 0 );

            $fi->setDimX( $product_data[ 'dimensions' ][ 'width' ] ?: null );
            $fi->setDimY( $product_data[ 'dimensions' ][ 'height' ] ?: null );
            $fi->setDimZ( $product_data[ 'dimensions' ][ 'length' ] ?: null );

            $fi->setWeight( $product_data[ 'weight' ] ?: null );

            $child[] = $fi;
        }
        return $child;
    }
}

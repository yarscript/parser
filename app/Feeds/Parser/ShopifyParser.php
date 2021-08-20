<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Helpers\StringHelper;

abstract class ShopifyParser extends HtmlParser
{
    protected const DISCOUNT = null;
    protected const MIN_PRICE = null;
    protected const EF_PARSE_PRODUCT_JSON = 'Cannot parse json: %s';

    protected function getMeta(): void
    {
        if ( $this->node->count() ) {
            $html = str_replace( [ '&quot;', '\"' ], [ '"', '&qout;' ], $this->node->html() );
            if ( preg_match_all( '/{"id":\d+,"title":".*?","handle":".*?","description":".*?".*?,"price":\d+,"price_min":\d+,"price_max":\d+,"available":[a-z]+,"price_varies":[a-z]+,"compare_at_price":([a-z]+|\d+),"compare_at_price_min":\d+,"compare_at_price_max":\d+,"compare_at_price_varies":[a-z]+,"variants":\[.*?],"content":".*?"}/', $html, $matches ) ) {
                if ( count( $matches[ 0 ] ) && count( array_unique( $matches[ 0 ] ) ) > 1 ) {
                    $products = array_filter( array_map( static fn( $product_json ) => json_decode( str_replace( '&quot;', '\"', $product_json ), true, 512, JSON_THROW_ON_ERROR ), $matches[ 0 ] ) );
                    array_map( function ( array $product ) {
                        if ( $this->getAttr( 'meta[property="og:title"]', 'content' ) === $product[ 'title' ] ) {
                            $this->meta = $product;
                        }
                    }, $products );
                }
                else {
                    $this->meta = json_decode( str_replace( '&quot;', '\"', $matches[ 0 ][ 0 ] ), true, 512, JSON_THROW_ON_ERROR ) ?? [];
                }
            }
            else {
                print PHP_EOL . sprintf( self::EF_PARSE_PRODUCT_JSON, $this->getUri() ) . PHP_EOL;
            }
        }
    }

    public function getProduct(): string
    {
        return $this->meta ? $this->meta[ 'title' ] : '';
    }

    public function getBrand(): ?string
    {
        return $this->meta ? $this->meta[ 'vendor' ] : '';
    }

    public function getMpn(): string
    {
        if ( !$this->isGroup() ) {
            return $this->meta ? str_replace( ' ', '-', StringHelper::removeSpaces( $this->meta[ 'variants' ][ 0 ][ 'sku' ] ?? '' ) ) : '';
        }
        return '';
    }

    public function getUpc(): string
    {
        $upc = '';

        if ( !$this->isGroup() ) {
            $upc = $this->meta ? $this->meta[ 'variants' ][ 0 ][ 'barcode' ] : '';
            $upc = str_replace( array( "'--", ' ' ), '', $upc );
        }
        return $upc;
    }

    public function getAvail(): ?int
    {
        if ( $this->meta ) {
            $amount = null;

            if ( !empty( $this->meta[ 'available' ] ) ) {
                $amount = $this->meta[ 'available' ] ? 1000 : 0;
            }
            if ( !$this->isGroup() ) {
                if ( isset( $this->meta[ 'variants' ][ 0 ][ 'inventory_quantity' ] ) && $this->meta[ 'variants' ][ 0 ][ 'inventory_quantity' ] > 0 ) {
                    $amount = $this->meta[ 'variants' ][ 0 ][ 'inventory_quantity' ];
                }
                elseif ( $this->meta[ 'variants' ][ 0 ][ 'available' ] === false ) {
                    $amount = 0;
                }
            }
            return $amount;
        }

        return parent::getAvail();
    }

    public function getInternalId(): string
    {
        return $this->getUri();
    }

    public function getDescription(): string
    {
        return $this->meta ? ( $this->meta[ 'description' ] ?? '' ) : parent::getDescription();
    }

    public function getCostToUs(): float
    {
        if ( $this->meta && !$this->isGroup() ) {
            return (float)$this->meta[ 'variants' ][ 0 ][ 'price' ] / 100 * ( static::DISCOUNT ?? 1 );
        }
        return parent::getCostToUs();
    }

    public function getListPrice(): ?float
    {
        if ( $this->meta && !$this->isGroup() ) {
            return (float)$this->meta[ 'variants' ][ 0 ][ 'compare_at_price' ] / 100;
        }
        return parent::getListPrice();
    }

    public function getForsale(): string
    {
        if ( $this->meta ) {
            return $this->meta[ 'type' ] === 'discontinued' ? 'N' : parent::getForsale();
        }
        return parent::getForsale();
    }

    public function isGroup(): bool
    {
        if ( $this->meta ) {
            return count( $this->meta[ 'variants' ] ?? [] ) > 1;
        }
        return false;
    }

    public function getChildMpn( string $mpn, array $child_variant ): string
    {
        return str_replace( ' ', '', $mpn );
    }

    public function parseVariations( $variants, FeedItem $item ): array
    {
        $child_products = $qtys = [];

        if ( preg_match_all( '/inv_qty\[(\d*)] = (\d*)/m', $this->node->html(), $matches ) ) {
            $qtys = array_combine( $matches[ 1 ], $matches[ 2 ] );
        }

        foreach ( $variants as $variant ) {
            $child_item = new FeedItem();
            $child_item->setMpn( $this->getChildMpn( $variant[ 'sku' ] ?? '', $variant ) );

            $child_item->setProductCode( $this->vendor->getPrefix() . $child_item->getMpn() );
            $child_item->setProduct( html_entity_decode( $variant[ 'title' ] ) );
            $child_item->setUpc( $variant[ 'barcode' ] );
            $child_item->setGroupMask( $item->getProduct() );
            $child_item->setBrandName( $item->getBrandName() );
            $child_item->setBrandNormalized( $this->getBrandNormalized() );
            $child_item->setWeight( round( $variant[ 'weight' ] / 100, 2 ) );
            $child_item->setSupplierInternalId( $item->getSupplierInternalId() );
            $child_item->setFulldescr( $item->getFulldescr() );
            $child_item->setCategories( $item->getCategories() );
            $child_item->setForsale( $this->getForsale() );

            $avail = $variant[ 'available' ] ? 1000 : 0;
            if ( isset( $variant[ 'inventory_quantity' ] ) ) {
                if ( (int)$variant[ 'inventory_quantity' ] > 0 ) {
                    $avail = $variant[ 'inventory_quantity' ];
                }
                else {
                    $avail = 0;
                }
            }
            $child_item->setRAvail( $avail );

            if ( isset( $variant[ 'id' ], $qtys[ $variant[ 'id' ] ] ) ) {
                $child_item->setRAvail( (int)$qtys[ $variant[ 'id' ] ] );
            }

            $cost = (float)$variant[ 'price' ] / 100;
            if ( $cost <= 0 ) {
                $cost = 10000;
            }

            if ( static::DISCOUNT !== null ) {
                $child_item->setCostToUs( round( $cost * static::DISCOUNT, 2 ) );
            }
            else {
                $child_item->setCostToUs( $cost );
            }

            if ( static::MIN_PRICE !== null ) {
                $child_item->setNewMapPrice( round( $cost * static::MIN_PRICE, 2 ) );
            }

            if ( $variant[ 'compare_at_price' ] ) {
                $child_item->setListPrice( (float)$variant[ 'compare_at_price' ] / 100 );
            }

            if ( $variant[ 'featured_image' ] && $variant[ 'featured_image' ][ 'src' ] ) {
                $child_item->setImages( [ $variant[ 'featured_image' ][ 'src' ] ] );
            }
            else {
                $child_item->setImages( $item->getImages() );
            }

            $child_products[] = $child_item;

        }
        return $child_products;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child_products = [];
        if ( $this->meta && ( $variants = $this->meta[ 'variants' ] ) && $this->isGroup() ) {
            $child_products = $this->parseVariations( $variants, $parent_fi );
        }
        return $child_products;
    }

    public function getImages(): array
    {
        $images = $this->meta[ 'images' ] ?? [];

        if ( !$images && $this->meta && $this->meta[ 'featured_image' ] ) {
            $images = [ $this->meta[ 'featured_image' ] ];
        }

        return array_map( static fn( $url ) => "https:{$url}", $images );
    }

    public function getCategories(): array
    {
        return $this->meta ? $this->meta[ 'tags' ] : parent::getCategories();
    }

    public function getWeight(): ?float
    {
        if ( $this->meta && !$this->isGroup() ) {
            return $this->meta[ 'variants' ][ 0 ][ 'weight' ] / 100;
        }
        return parent::getWeight();
    }
}

<?php

namespace App\Feeds\Vendors\HSD;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private const MAIN_DOMAIN = 'https://www.hotstuffdropship.com/store/';

    private array $dims = [];
    private array $shorts = [];
    private ?array $attrs = null;
    private ?float $shipping_weight = null;
    private ?float $list_price = null;
    private ?int $avail = null;
    private string $mpn = '';
    private string $product = '';

    public function beforeParse(): void
    {
        $body = $this->getHtml( 'div#productDescription' );

        $arr = explode( '<br>', $body );
        foreach ( $arr as $val ) {
            $val = strip_tags( $val );
            if ( str_contains( $val, '_' ) ) {
                $this->mpn = $val;
            }
            elseif ( str_contains( $val, ' x ' ) ) {
                $ar = explode( 'x', $val );
                $this->dims[ 'x' ] = StringHelper::getFloat( $ar[ 0 ] );
                $this->dims[ 'y' ] = StringHelper::getFloat( $ar[ 1 ] );

            }
            elseif ( stripos( $val, 'Retail Price' ) !== false ) {
                $this->list_price = StringHelper::getMoney( $val );
            }
            elseif ( stripos( $val, 'Not for sale' ) !== false ) {
                $this->shorts[] = StringHelper::normalizeSpaceInString( $val );
            }
        }
        $this->filter( 'ul#productDetailsList li' )->each( function ( ParserCrawler $c ) {
            if ( str_contains( $c->text(), ':' ) ) {
                [ $key, $val ] = explode( ':', $c->text() );
                if ( stripos( $key, 'Shipping Weight' ) !== false ) {
                    $this->shipping_weight = StringHelper::getFloat( $val );
                }
                elseif ( stripos( $key, 'Model' ) !== false ) {
                    $this->product = StringHelper::normalizeSpaceInString( $val );
                }
                else {
                    $this->attrs[ StringHelper::normalizeSpaceInString( $key ) ] = StringHelper::normalizeSpaceInString( $val );
                }
            }
            elseif ( stripos( $c->text(), 'in stock' ) !== false ) {
                $this->avail = StringHelper::getFloat( $c->text() );
            }
            elseif ( stripos( $c->text(), 'out of stock' ) !== false ) {
                $this->avail = 0;
            }
            else {
                $this->shorts[] = StringHelper::normalizeSpaceInString( $c->text() );
            }
        } );
    }

    public function getMpn(): string
    {
        return $this->mpn;
    }

    public function getProduct(): string
    {
        return $this->product ?: $this->getText( 'h1#productName' );
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney( $this->getMoney( 'h2#productPrices' ) );
    }

    public function getImages(): array
    {
        return [ self::MAIN_DOMAIN . $this->getAttr( 'div#productMainImage img', 'src' ) ];
    }

    public function getDimX(): ?float
    {
        return $this->dims[ 'x' ] ?? null;
    }

    public function getDimY(): ?float
    {
        return $this->dims[ 'y' ] ?? null;
    }

    public function getShortDescription(): array
    {
        return $this->shorts;
    }

    public function getAttributes(): ?array
    {
        return $this->attrs ?? null;
    }

    public function getListPrice(): ?float
    {
        return $this->list_price;
    }

    public function getShippingWeight(): ?float
    {
        return $this->shipping_weight;
    }

    public function getAvail(): ?int
    {
        return $this->avail;
    }
}
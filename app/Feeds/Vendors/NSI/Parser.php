<?php

namespace App\Feeds\Vendors\NSI;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    public function getMpn(): string
    {
        return $this->getText( '#sku' );
    }

    public function getProduct(): string
    {
        return trim( $this->getAttr( 'meta[property="og:title"]', 'content' ) );
    }

    public function getListPrice(): ?float
    {
        if ( $this->exists( '.pcShowProductListPrice' ) ) {
            return $this->getMoney( '.pcShowProductListPrice' );
        }

        return StringHelper::getMoney( $this->getAttr( 'meta[ itemprop="price"]', 'content' ) );
    }

    public function getCostToUs(): float
    {
        if ( $this->exists( '.pcShowProductSalePrice' ) ) {
            return $this->getMoney( '.pcShowProductSalePrice' );
        }

        return StringHelper::getMoney( $this->getAttr( 'meta[ itemprop="price"]', 'content' ) );
    }

    public function getDescription(): string
    {
        $description = trim( $this->getHtml( '[itemprop="description"]' ) );
        if ( $description === '' ) {
            $description = trim( $this->getText( '#details [style="font-size: 12pt; font-family: Arial;"]' ) );
        }
        return $description;
    }

    public function getShortDescription(): array
    {
        if ( $this->exists( '#details ul' ) ) {
            return $this->getContent( '#details ul li' );
        }
        return [];
    }

    public function getImages(): array
    {
        return array_values( array_unique( $this->getLinks( 'a.highslide' ) ) );
    }

    public function getCategories(): array
    {
        $categories = $this->getContent( '.pcPageNav a' );
        array_shift( $categories );
        return $categories;
    }

    public function getOptions(): array
    {
        $options = [];
        $option_lists = $this->filter( '.pcShowProductOptions' );

        if ( !$option_lists->count() ) {
            return $options;
        }

        $option_lists->each( function ( ParserCrawler $list ) use ( &$options ) {
            $label = $list->filter( 'label' );
            if ( $label->count() === 0 ) {
                return;
            }
            $name = trim( $label->text(), ' : ' );
            $options[ $name ] = [];
            $list->filter( 'option' )->each( function ( ParserCrawler $option ) use ( &$options, $name ) {
                $options[ $name ][] = trim( $option->text(), '  ' );
            } );
        } );

        return $options;
    }

    public function getBrand(): ?string
    {
        return $this->getAttr( '.brand img', 'alt' );
    }

    public function getAvail(): ?int
    {
        return self::DEFAULT_AVAIL_NUMBER;
    }
}

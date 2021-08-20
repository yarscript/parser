<?php

namespace App\Feeds\Processor;

use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use Symfony\Component\DomCrawler\Crawler;

abstract class SitemapHttpProcessor extends HttpProcessor
{
    /**
     * An array of css selectors that select elements of links (<a>) to product categories for their further traversal
     */
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'sitemap loc' ];
    /**
     * An array of css selectors that select link elements (<a>) to product pages to collect information from them
     */
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'loc' ];

    /**
     * Returns all links to category pages that were found by the selectors specified in the constant "CATEGORY_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getCategoriesLinks( Data $data, string $url ): array
    {
        $result = [];
        $crawler = new Crawler( $data->getData() );

        foreach ( static::CATEGORY_LINK_CSS_SELECTORS as $css ) {
            if ( $links = $crawler->filter( $css )->each( static function ( Crawler $node ) {
                return new Link( $node->text() );
            } ) ) {
                array_push( $result, ...$links );
            }
        }

        return $result;
    }

    /**
     * Returns all links to product pages that were found by the selectors specified in the constant "PRODUCT_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getProductsLinks( Data $data, string $url ): array
    {
        if ( preg_match_all( '/<loc>([^<]*)<\/loc>/m', $data->getData(), $matches ) ) {
            $links = array_map( static fn( $url ) => new Link( htmlspecialchars_decode( $url ) ), $matches[ 1 ] );
        }

        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }
}
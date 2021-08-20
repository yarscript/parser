<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Traits\ParserTrait;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use RuntimeException;

/**
 * @method exists( string $selector )
 * @method filter( string $selector, ?int $index = null )
 * @method getText(string $selector)
 * @method getMoney( string $selector )
 * @method getHtml( string $selector )
 * @method getLinks( string $selector )
 * @method getContent( string $selector )
 * @method getAttr( string $selector, string $attr )
 * @method getAttrs( string $selector, string $attr )
 * @method getSrcImages( string $selector )
*/
abstract class HtmlParser implements ParserInterface
{
    use ParserTrait;
    /**
     * @param string current page url
     */
    private ?string $uri = null;
    /**
     * @var array meta-information of the site, stores additional info. for the website
     */
    protected array $meta = [];
    /**
     * @var ParserCrawler Abstraction over Symfony\Component\DomCrawler\Crawler
     */
    protected ParserCrawler $node;

    /**
     * @param Data $data
     * @param array $params
     * @return FeedItem[]
     */
    public function parseContent( Data $data, array $params = [] ): array
    {
        $this->node = new ParserCrawler( $data->getData(), $params[ 'url' ] ?? '' );
        $this->uri = $params[ 'url' ] ?? '';
        $this->getMeta();

        $item = new FeedItem( $this );

        $mpn = $item->isGroup() ? md5( microtime() . mt_rand() ) : $item->mpn;

        return [ $mpn => $item ];
    }

    public static function untexturize( $fancy ): string
    {
        $fixes = array(
            json_decode( '"\u201C"' ) => '"',     // left  double quotation mark
            json_decode( '"\u201D"' ) => '"',     // right double quotation mark
            json_decode( '"\u2018"' ) => "'",     // left  single quotation mark
            json_decode( '"\u2019"' ) => "'",     // right single quotation mark
            json_decode( '"\u2032"' ) => "'",     // prime (minutes, feet)
            json_decode( '"\u2033"' ) => '"',     // double prime (seconds, inches)
            json_decode( '"\u2013"' ) => '-',     // en dash
            json_decode( '"\u2014"' ) => '--',    // em dash
        );

        return strtr( $fancy, $fixes );
    }

    protected function getMeta(): void
    {

    }

    /**
     * Get product uri
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    protected function console( $message ): void
    {
        echo PHP_EOL . $this->node->getUri();
        echo PHP_EOL . $message . PHP_EOL;
    }

    public function __call( $name, $parameters )
    {
        if ( method_exists( $this->node, $name ) ) {
            return $this->node->$name( ...$parameters );
        }

        throw new RuntimeException( "Unknown $name method" );
    }
}

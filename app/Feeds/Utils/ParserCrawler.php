<?php

namespace App\Feeds\Utils;

use App\Helpers\StringHelper;
use Symfony\Component\DomCrawler\Crawler;

class ParserCrawler extends Crawler
{
    private string $content;
    private ?string $type = null;

    /**
     * search elements by selector in self context
     * @param string $selector - css selector for search in self context
     * @param int|null $index - get one element when selector matches on many elements
     * @return ParserCrawler
     */
    public function filter( string $selector, ?int $index = null ): ParserCrawler
    {
        if ( $index !== null ) {
            return parent::filter( $selector )->eq( $index );
        }

        return parent::filter( $selector );
    }

    /**
     * get text from current context filtered by selector
     * @param string $selector - filter selector
     * @return string
     */
    public function getText( string $selector ): string
    {
        $elem = $this->filter( $selector );
        return $elem->count() ? html_entity_decode( $elem->text() ) : '';
    }

    /**
     * get money number from context by selector
     * based on getText but apply StringHelper::getMoney
     * @param string $selector - filter selector
     * @return float
     */
    public function getMoney( string $selector ): float
    {
        return StringHelper::getMoney( $this->getText( $selector ) );
    }

    /**
     * get html from current context filtered by selector
     * @param string $selector - filter selector
     * @return string html
     */
    public function getHtml( string $selector ): string
    {
        $elem = $this->filter( $selector );
        return $elem->count() ? html_entity_decode( $elem->html() ) : '';
    }

    /**
     * get all unique links from context filtered by selector
     * @param string $selector - filter selector
     * @return string[] - array of links
     */
    public function getLinks( string $selector ): array
    {
        $all_found_urls = $this->filter( $selector )->each( static fn( ParserCrawler $node ) => $node->link()->getUri() );
        return array_values( array_unique( $all_found_urls ) );
    }

    /**
     * get all unique text from context filtered by selector
     * @param string $selector - filter selector
     * @return string[] - array of every element text contents
     */
    public function getContent( string $selector ): array
    {
        return $this->filter( $selector )->each( static fn( ParserCrawler $node ) => trim( $node->text() ) );
    }

    /**
     * get attribute value from context filtered by selector
     * @param string $selector - filter selector
     * @param string $attr - attribute name
     * @return string - attribute value
     */
    public function getAttr( string $selector, string $attr ): string
    {
        $elem = $this->filter( $selector );
        return $elem->count() ? $elem->attr( $attr ) ?? '' : '';
    }

    /**
     * equal getAttr but for many elements
     * @param string $selector - filter selector
     * @param string $attr - attribute name
     * @return string[] - attribute values
     */
    public function getAttrs( string $selector, string $attr ): array
    {
        if ( $i = $this->filter( $selector ) ) {
            $values = $i->each( static fn( ParserCrawler $node ) => $node->attr( $attr ) );
            return array_values( array_filter( array_unique( $values ) ) );
        }
        return [];
    }

    /**
     * equal getAttrs but attribute href always and select only from img elements
     * @param string $selector filter selector
     * @return string[]
     */
    public function getSrcImages( string $selector ): array
    {
        return $this->filter( $selector )->each( static fn( ParserCrawler $node ) => $node->image()->getUri() );
    }

    /**
     * know have element children matches by selector
     * @param string $selector
     * @return bool
     */
    public function exists( string $selector ): bool
    {
        return (bool)$this->filter( $selector )->count();
    }

    public function addContent( string $content, string $type = null ): void
    {
        $this->content = $content;
        $this->type = $type;
        parent::addContent( $this->content, $this->type );
    }

    public function json(): array
    {
        return $this->content ? json_decode( $this->content, true, 512, JSON_THROW_ON_ERROR ) : [];
    }
}

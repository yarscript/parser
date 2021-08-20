<?php

namespace App\Feeds\Utils;

class Collection extends \Illuminate\Support\Collection
{
    public const LINK_TYPE_CATEGORY = 'category';
    public const LINK_TYPE_PRODUCT = 'product';

    /**
     * Add links to Collection
     * @param string[]|Link[] $links
     * @param string $type
     * @return void
     */
    public function addLinks( array $links, string $type ): void
    {
        $links = array_map( static fn( $link ) => $link instanceof Link ? $link : new Link( (string)$link ), $links );

        foreach ( $links as $link ) {
            if ( !$this->has( $link->getUrl() ) ) {
                $this->put( $link->getUrl(), [ 'link' => $link, 'type' => $type ] );
            }
        }
        $this->sortBy( 'type' );
    }

    /**
     * Clear collection
     * @return Collection
     */
    public function clear(): Collection
    {
        return $this->reject( fn() => true );
    }

    /**
     * Get next unvisited links
     * @param $type
     * @param int $count
     * @return Link[]
     */
    public function next( $type, int $count = 1 ): array
    {
        $queue = $this
            ->filter( fn( $value ) => !$value[ 'link' ]->isVisited() && ( $type === null || $value[ 'type' ] === $type ) )
            ->slice( 0, $count )
            ->all();
        return array_map( static fn( $element ) => $element[ 'link' ], $queue );
    }
}

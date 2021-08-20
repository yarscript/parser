<?php

namespace App\Feeds\Traits;

use Exception;
use App\Feeds\Utils\Link;

trait JsonTrait
{
    /**
     * load array of Links
     * @param Link[] $links
     * @return array[]
     * @throws Exception
     */
    protected function fetchLinks( array $links ): array
    {
        return array_combine(
            array_map( static fn( $link ) => $link->getUrl(), $links ),
            array_map( fn( $link ) => $this->getDownloader()->get( $link ), $links )
        );
    }
}

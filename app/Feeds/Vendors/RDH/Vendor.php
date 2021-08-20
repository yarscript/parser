<?php

namespace App\Feeds\Vendors\RDH;

use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends SitemapHttpProcessor
{
    protected array $first = [ 'https://readyhour.com/sitemap.xml' ];

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains( $link->getUrl(), '/products/' );
    }
}

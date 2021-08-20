<?php

namespace App\Feeds\Vendors\NSI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ '#menu > li > a', '.pcShowCategoryName a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ '.pcShowProductName a' ];

    protected const CHUNK_SIZE = 30;

    protected array $first = [ 'https://www.northshoreinc.com/store/pc/home.asp' ];

    public function getCategoriesLinks( Data $data, string $url ): array
    {
        return array_map(
            static fn( Link $link ) => new Link( $link->getUrl() . '&viewAll=yes' ),
            parent::getCategoriesLinks( ...func_get_args() )
        );
    }

    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        return count( $fi->getImages() ) && $fi->getCostToUs() > 0;
    }
}

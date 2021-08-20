<?php

namespace App\Feeds\Vendors\HSD;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'div#allProductsListingTopLinks a' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'tr.productListing-odd a:first-child' ];

    protected array $first = [ 'https://www.hotstuffdropship.com/store/index.php?main_page=products_all&zenid=d5n9lkqqion3bbn9rmba16io90' ];

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !empty( $fi->getMpn() );
    }
}
<?php

namespace App\Feeds\Vendors\JSP;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\HttpProcessor;
use App\Feeds\Storage\AbstractFeedStorage;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Repositories\DxRepositoryInterface;

class Vendor extends HttpProcessor
{
    public const CATEGORY_LINK_CSS_SELECTORS = [ 'li.menu-item a', 'ul.wl-pagination a.node' ];
    public const PRODUCT_LINK_CSS_SELECTORS = [ 'div.info p.name a' ];
//    protected const DELAY_S = 1;
    protected const CHUNK_SIZE = 30;

    public const DX_PREFIX = 'JSP';
    public const DX_NAME = 'jefferspet';
    public const DX_ID = 1;

    protected array $first = [ 'https://www.jefferspet.com' ];

/*    public array $custom_products = [
        'https://www.jefferspet.com/products/jeffers-expression-maya-lycra-fly-mask',
        'https://www.jefferspet.com/products/le-bol-pet-bowls',
        'https://www.jefferspet.com/products/pooch-pad-indoor-turf-dog-potty-replacement-pad',
        'https://www.jefferspet.com/products/stainless-steel-embossed-wide-lip-bowls'
    ];*/

    protected ?int $max_products = 100;

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        if ( $fi->isGroup() ) {
            $fi->setChildProducts( array_values(
                array_filter( $fi->getChildProducts(), static fn( FeedItem $item ) => !empty( $item->getMpn() ) && count( $item->getImages() ) )
            ) );
            return count( $fi->getChildProducts() );
        }
        return !empty( $fi->getMpn() ) && count( $fi->getImages() );
    }
}
<?php

namespace App\Feeds\Storage;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\AbstractProcessor;
use DateTime;

abstract class AbstractFeedStorage
{
    protected AbstractProcessor $processor;
    protected DateTime $start;

    public function __construct()
    {
        $this->start = new DateTime();
    }

    /**
     * @param AbstractProcessor $processor
     * @param FeedItem[] $items
     */
    abstract public function saveFeed(AbstractProcessor $processor, array $items): void;

    /**
     * @param FeedItem[] $items
     * @return array
     */
    protected function getData( array $items ): array
    {
        $result = [];
        foreach ( $items as $item ) {
            $attrs = $this->processor->getFeedType() === $this->processor::FEED_TYPE_INVENTORY
                ? $item->propsToArray(
                    [
                        'cost_to_us',
                        'new_map_price',
                        'productcode',
                        'r_avail',
                        'min_amount',
                        'forsale',
                        'eta_date_mm_dd_yyyy',
                        'upc'
                    ] )
                : $item->propsToArray();

            $attrs[ 'brand_name' ] = empty( $attrs[ 'brand_name' ] ) ? $this->processor->getSupplierName() : $attrs[ 'brand_name' ];

            $result[] = $attrs;
        }

        return $result;
    }

    protected function getProcessTime(): int
    {
        return ( new DateTime() )->getTimestamp() - $this->start->getTimestamp();
    }

    public function shutdown():void
    {

    }
}
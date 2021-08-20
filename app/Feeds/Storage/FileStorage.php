<?php

namespace App\Feeds\Storage;

use App\Feeds\Processor\AbstractProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class FileStorage extends AbstractFeedStorage
{
    private const FEED_FILE_EXTENSION = 'json';
    public function saveFeed( AbstractProcessor $processor, array $items ): void
    {
        $this->processor = $processor;
        if ( count( $items ) ) {
            $json = $this->prepareJSON( $items );

            Storage::disk( 'local' )->put( $this->getFileName( self::FEED_FILE_EXTENSION ), $json );

            $this->saveMd5( md5( $json ) );
        }
    }

    private function saveMd5( $md5 ): void
    {
        Storage::disk( 'local' )->put( $this->getFileName( 'md5' ), $md5 );
            }

    private function getDefaults(): array
    {
        return [
            'dim_x' => 0,
            'dim_y' => 0,
            'dim_z' => 0,
            'weight' => 0,
            'min_amount' => 1,
            'mult_order_quantity' => 'N',
            'shipping_freight' => 0.01,
            'discount_avail' => 'Y',
            'low_avail_limit' => 1000,
            'free_tax' => 'Y',
            'discount_slope' => 0.6,
            'discount_table' => '2,3,4,6,8,12',
            'free_ship_zone' => -1,
            'free_ship_text' => '',
            'lead_time_message' => '',
            'pc_classify_status' => 'NC',
            'provider' => 'feed',
            'product_type' => 'N',
            'update_search_index' => 'Y'
        ];
    }

    private function getDontUpdateFields(): array
    {
        if ( ( $feeds = $this->processor->dx_info[ 'feeds' ] ?? [] ) && count( $feeds ) === 1 ) {
            $feed = reset( $feeds );
            return $feed[ 'dont_update_fields' ] ?? [];
        }
        return [];
    }

    private function prepareJSON( $items ): string
    {
        $result = [];
        $result[ 'supplier_id' ] = $this->processor->getSupplierId();
        $result[ 'supplier_name' ] = $this->processor->getSupplierName();
        $result[ 'original_url' ] = $this->processor->getSource();
        $result[ 'create_date' ] = date( 'm-d-Y-H-i-s' );
        $result[ 'feed_source' ] = $this->processor->getFeedSource();
        $result[ 'feed_source_date' ] = $this->processor->getFeedDate()->format( 'Y-m-d H:i:s' );
        $result[ 'feed_type' ] = $this->processor->getFeedType();
        $result[ 'process_time' ] = $this->getProcessTime();
        $result[ 'products_in_feed' ] = $this->getCountItems( $items );
        if ( $this->processor->getFeedType() === $this->processor::FEED_TYPE_PRODUCT ) {
            $result[ 'defaults' ] = $this->getDefaults();
            $result[ 'dont_update_fields' ] = $this->getDontUpdateFields();
        }

        $result[ 'products' ] = $this->getData( $items );

        return ( new JsonResponse( $result, 200, [], $this->processor->isDevMode() ? JSON_PRETTY_PRINT : 0 ) )->getContent();
    }

    private function getFileName( $ext = 'txt' ): string
    {
        if ( ( $feeds = $this->processor->dx_info[ 'feeds' ] ?? [] ) && count( $feeds ) === 1 ) {
            $feed = reset( $feeds );
            if ( $file = $feed[ 'feed_file_name' ] ?? null ) {
                $path = pathinfo( $file );
                $result = "{$path['filename']}.{$ext}";
            }
        }
        return $result ?? "feed{$this->processor->getSupplierId()}{$this->processor->getFeedType()[0]}.{$ext}";
    }

    private function getCountItems( array $items ): int
    {
        $count = 0;
        foreach ( $items as $item ) {
            if ( $item->isGroup() ) {
                $count += count( $item->getChildProducts() );
            }
            else {
                ++$count;
            }
        }
        return $count;
    }
}

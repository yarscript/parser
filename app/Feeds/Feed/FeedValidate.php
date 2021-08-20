<?php

namespace App\Feeds\Feed;

use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Storage;
use Ms48\LaravelConsoleProgressBar\Facades\ConsoleProgressBar;

class FeedValidate
{
    protected FeedItem $current_item;
    private string $dx;
    private array $fails = [];

    private bool $fail = false;

    public function __construct( array $feed_items, $dx_info )
    {
        print PHP_EOL . 'Validate feeds' . PHP_EOL;
        $this->dx = rtrim( $dx_info[ 'prefix' ] ?? '', '-' );

        $this->validateItems( $feed_items );
        if ( $this->fail ) {
            $this->saveValidateErrors();
            print PHP_EOL . 'Validate fail. Check storage/app/logs/' . $this->dx . '_error.log for more information';
        }
        else {
            $this->removeValidateErrors();
            print PHP_EOL . 'Validate complete';
        }
    }

    private function validateItems( array $feed_items ): void
    {
        $count_items = 1;
        foreach ( $feed_items as $feed_item ) {
            if ( $feed_item->isGroup() && count( $feed_item->getChildProducts() ) ) {
                $count_items += count( $feed_item->getChildProducts() );
                foreach ( $feed_item->getChildProducts() as $child_item ) {
                    $this->validateItem( $child_item );
                }
                ConsoleProgressBar::showProgress( count( $feed_item->getChildProducts() ), $count_items );
            }
            else {
                ++$count_items;
                ConsoleProgressBar::showProgress( 1, $count_items );
            }
            $this->validateItem( $feed_item );
        }
        ConsoleProgressBar::showProgress( 0, $count_items - 1 );
    }

    private function setCurrentItem( FeedItem $item ): void
    {
        $this->current_item = $item;
    }

    private function getMpnCurrentProduct(): string
    {
        if ( $this->current_item->getMpn() ) {
            return "mpn product: {$this->current_item->getMpn()}";
        }
        return '';
    }

    private function getErrorHeader( string $header_key, array $fails ): string
    {
        return "\nFail validate " . count( $fails ) . " $header_key in products:";
    }

    private function saveValidateErrors(): void
    {
        $errors = [];
        foreach ( $this->fails as $fail_type => $fail ) {
            array_filter( $fail );
            $errors[] = $this->getErrorHeader( $fail_type, $fail );
            $errors = (array)array_merge( $errors, $fail );
        }
        $error_str = implode( "\n", $errors );
        Storage::put( "logs/{$this->dx}_error.log", $error_str );
    }

    private function removeValidateErrors(): void
    {
        $file_log = "logs/{$this->dx}_error.log";
        if ( Storage::exists( $file_log ) ) {
            Storage::delete( $file_log );
        }
    }

    private function attachFailProduct( string $fail_type, string $message ): void
    {
        $this->fail = true;
        $this->fails[ $fail_type ][] = $this->getMpnCurrentProduct() . ' - ' . $message;
    }

    private function findPriceInString( string $string ): ?string
    {
        return StringHelper::existsMoney( $string ) ?: null;
    }

    private function validateItem( FeedItem $item ): void
    {
        $this->setCurrentItem( $item );

        $this->validateProductName( $item->getProduct(), $item->isGroup() );
        $this->validateCostToUs( $item->getCostToUs(), $item->isGroup() );
        $this->validateListPrice( $item->getListPrice(), $item->isGroup() );
        $this->validateCategories( $item->getCategories() );
        $this->validateShortDesc( $item->getShortdescr() );
        $this->validateDescription( $item->getFulldescr() );
        $this->validateImages( $item->getImages(), $item->isGroup() );
        $this->validateAvail( $item->getRAvail() );
        $this->validateMpn( $item->mpn, $item->isGroup() );
        $this->validateAttributes( $item->attributes );
        $this->validateProductFiles( $item->getProductFiles() );
        $this->validateVideos( $item->getVideos() );
        $this->validateOptions( $item->getOptions() );

        if ( $item->isGroup() ) {
            $this->validateChildProducts( $item->getChildProducts() );
        }
    }

    private function validateChildProducts( array $child_products ): void
    {
        if ( !count( $child_products ) ) {
            $this->attachFailProduct( 'child_products', 'The group product does not have any children' );
        }
    }

    private function validateProductName( string $product_name, bool $group ): void
    {
        if ( $group ) {
            if ( $product_name === 'Dummy' ) {
                $this->attachFailProduct( 'product_name', 'Invalid group product name' );
            }
        }
        else if ( $product_name === "" || $product_name === 'Dummy' ) {
            $this->attachFailProduct( 'product_name', 'Invalid product name' );
        }
        elseif ( $currency = $this->findPriceInString( $product_name ) ) {
            $this->attachFailProduct( 'product_name', 'Product name contains ' . $currency );
        }
        elseif ( $product_name !== strip_tags( $product_name ) ) {
            $this->attachFailProduct( 'product_name', 'Product name contains html tags' );
        }
    }

    private function validateCostToUs( float $cost, bool $group ): void
    {
        if ( !$group && $cost <= 0 ) {
            $this->attachFailProduct( 'cost_to_us', 'Cost to us cannot be less than or equal to zero' );
        }
    }

    private function validateListPrice( ?float $list, bool $group ): void
    {
        if ( !$group && $list <= 0 && !is_null( $list ) ) {
            $this->attachFailProduct( 'list_price', 'List price cannot be less than or equal to zero' );
        }
    }

    private function validateCategories( array $categories ): void
    {
        $filter_categories = array_filter( $categories );
        if ( count( $categories ) > count( $filter_categories ) ) {
            $this->attachFailProduct( 'categories', 'The category array contains empty values' );
        }

        if ( array_values( $categories ) !== $categories ) {
            $this->attachFailProduct( 'categories', 'The sequence of keys in the category array is broken' );
        }

        if ( count( $categories ) > 5 ) {
            $this->attachFailProduct( 'categories', 'The number of categories can not be more than 5' );
        }
    }

    private function validateShortDesc( ?string $desc ): void
    {
        if ( is_null( $desc ) ) {
            return;
        }
        if ( $currency = $this->findPriceInString( $desc ) ) {
            $this->attachFailProduct( 'short_desc', 'The product short description contains ' . $currency );
        }
        if ( substr_count( $desc, '<ul>' ) > 1 ) {
            $this->attachFailProduct( 'short_desc', 'The product short description contains extra html tags' );
        }
    }

    private function validateDescription( string $desc ): void
    {
        $data = FeedHelper::getShortsAndAttributesInDesc( $desc );
        if ( !is_null( $data[ 'attributes' ] ) ) {
            $this->attachFailProduct( 'description', 'The product description contains a set of specifications' );
        }

        if ( count( $data[ 'short_desc' ] ) ) {
            $this->attachFailProduct( 'description', 'The product description contains a set of features' );
        }

        if ( $currency = $this->findPriceInString( $data[ 'description' ] ) ) {
            $this->attachFailProduct( 'description', 'The product description contains ' . $currency );
        }
    }

    private function validateImages( array $images, bool $group ): void
    {
        if ( !$group && !count( $images ) ) {
            $this->attachFailProduct( 'images', 'The product has no images' );
            return;
        }

        $filter_images = array_filter( $images );
        if ( count( $images ) > count( $filter_images ) ) {
            $this->attachFailProduct( 'images', 'The image array contains empty values' );
            return;
        }

        if ( array_values( $images ) !== $images ) {
            $this->attachFailProduct( 'images', 'The sequence of keys in the image array is broken' );
            return;
        }

        if ( count( $images ) !== count( array_unique( $images ) ) ) {
            $this->attachFailProduct( 'images', 'The product contains duplicate images' );
        }

        foreach ( $images as $image ) {
            if ( !str_contains( $image, 'http:/' ) && !str_contains( $image, 'https:/' ) ) {
                $this->attachFailProduct( 'images', 'The image link address must contain the http or https protocol' );
            }
            elseif ( $image === 'http://' || $image === 'https://' ){
                $this->attachFailProduct( 'images', 'The image link address contain only http or https protocol' );
            }
        }
    }

    private function validateAvail( ?int $avail ): void
    {
        if ( is_null( $avail ) ) {
            $this->attachFailProduct( 'avail', 'Avail is null' );
        }
    }

    private function validateMpn( string $mpn, bool $group ): void
    {
        if ( !$group && empty( $mpn ) ) {
            $this->attachFailProduct( 'mpn', 'Mpn must not be empty' );
        }
    }

    private function validateAttributes( ?array $attributes ): void
    {
        if ( !is_null( $attributes ) ) {
            if ( !count( $attributes ) ) {
                $this->attachFailProduct( 'attributes', 'The attribute array must not be empty' );
                return;
            }

            if ( count( array_filter( $attributes, static fn( $attribute ) => trim( $attribute ) !== '' ) ) !== count( $attributes ) ) {
                $this->attachFailProduct( 'attributes', 'The attribute array contains empty values' );
            }
            else {
                foreach ( $attributes as $key => $value ) {
                    if ( preg_match( '/(\d+\.\d+|\.\d+|\d+)/', $key, $match_key ) && $match_key[ 1 ] === trim( $key ) ) {
                        $this->attachFailProduct( 'attributes', 'The attribute name has a numeric format' );
                    }
                    if ( is_array( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'The attribute value must not be an array' );
                    }
                    if ( is_null( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'The attribute value must not be an null' );
                    }
                    if ( $currency = $this->findPriceInString( $value ) ) {
                        $this->attachFailProduct( 'attributes', 'The attribute value contains ' . $currency );
                    }
                    if ( trim( $value ) === '' || mb_strlen( $value, 'utf8' ) > 500 ) {
                        $this->attachFailProduct( 'attributes', 'The length of the attribute value is zero or exceeds 500 characters' );
                    }
                }
            }
        }
    }

    private function validateProductFiles( array $files ): void
    {
        if ( count( $files ) ) {
            foreach ( $files as $file ) {
                if ( !is_array( $file ) || count( $file ) !== 2 ) {
                    $this->attachFailProduct( 'product_files', 'The file array has an invalid format' );
                }
                elseif ( !array_key_exists( 'name', $file ) || !array_key_exists( 'link', $file ) ) {
                    $this->attachFailProduct( 'product_files', 'The file array has an invalid format' );
                }
            }
        }
    }

    private function validateOptions( array $options ): void
    {
        if ( count( $options ) ) {
            if ( count( array_filter( $options ) ) !== count( $options ) ) {
                $this->attachFailProduct( 'options', 'The options array contains empty values' );
            }
            else {
                foreach ( $options as $key => $values ) {
                    if ( preg_match( '/(\d+\.\d+|\.\d+|\d+)/', $key, $match_key ) && $match_key[ 1 ] === trim( $key ) ) {
                        $this->attachFailProduct( 'options', 'The options name has a numeric format' );
                    }
                    if ( empty( trim( $key ) ) || str_contains( $key, ':' ) || stripos( $key, 'required' ) !== false ) {
                        $this->attachFailProduct( 'options', 'The option name contains an empty value or forbidden characters' );
                    }

                    if ( is_array( $values ) ) {
                        if ( count( array_filter( $values ) ) !== count( $values ) ) {
                            $this->attachFailProduct( 'options', 'Option value is empty' );
                        }
                    }
                    else if ( empty( trim( $values ) ) ) {
                        $this->attachFailProduct( 'options', 'Option value is empty' );
                    }
                }
            }
        }
    }

    private function validateVideos( array $videos ): void
    {
        if ( count( $videos ) ) {
            foreach ( $videos as $video ) {
                if ( !is_array( $video ) || count( $video ) !== 3 ) {
                    $this->attachFailProduct( 'videos', 'The video array has an invalid format' );
                }

                if ( !array_key_exists( 'name', $video ) || !array_key_exists( 'video', $video ) || !array_key_exists( 'provider', $video ) ) {
                    $this->attachFailProduct( 'videos', 'The video array has an invalid format' );
                }
            }
        }
    }
}

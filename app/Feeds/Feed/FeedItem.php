<?php

namespace App\Feeds\Feed;

use App\Feeds\Parser\HtmlParser;
use App\Feeds\Parser\ParserInterface;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

class FeedItem
{
    /**
     * @var string Unique product code
     */
    public string $productcode = '';
    /**
     * @var string|null Product ID in Amazon
     */
    public ?string $ASIN = null;
    /**
     * @var string Product name
     */
    public string $product = '';
    /**
     * @var float|null Product price including discount
     */
    public ?float $cost_to_us = null;
    /**
     * @var float|null Original product price
     */
    public ?float $list_price = null;
    /**
     * @var string|null Key product features known as "features" or "bullets"
     */
    public ?string $descr = null;
    /**
     * @var string|null Product Description
     */
    public ?string $fulldescr = null;
    /**
     * @var string|null Product brand
     */
    public ?string $brand_name = null;
    /**
     * @var bool Is the product brand present in the product name
     */
    public bool $brand_normalized = false;
    /**
     * @var string Is it possible to sell the product "Y/N"
     */
    public string $forsale = 'Y';
    /**
     * @var string|null Date of receipt of the goods in the warehouse
     */
    public ?string $eta_date_mm_dd_yyyy = null;
    /**
     * @var string|null Product barcode
     */
    public ?string $upc = null;
    // category
    public array $supplier_categories = [];
    /**
     * @var string|null Link to the product page
     */
    public ?string $supplier_internal_id = null;
    /**
     * @var string Hash of the product amount
     */
    public string $hash_product = '';
    /**
     * @var array Links to product images
     */
    public array $images = [];
    /**
     * @var array Alternative product image names for the "alt" attribute
     */
    public array $alt_names = [];
    /**
     * @var float|null x,y, z product dimensions
     */
    public ?float $dim_x = null;
    public ?float $dim_y = null;
    public ?float $dim_z = null;
    /**
     * @var float|null Product weight for delivery (gross)
     */
    public ?float $shipping_weight = null;
    /**
     * @var float|null Dimensions of the product for delivery
     */
    public ?float $shipping_dim_x = null;
    public ?float $shipping_dim_y = null;
    public ?float $shipping_dim_z = null;
    /**
     * @var float|null Product weight (net)
     */
    public ?float $weight = null;
    /**
     * @var int Minimum quantity of product purchase
     */
    public int $min_amount = 1;
    /**
     * @var string|null Sell the product in a package or piece by piece "Y/N"
     */
    public ?string $mult_order_quantity = null;
    /**
     * @var bool Is the product a group product
     */
    public bool $is_group = false;
    /**
     * @var FeedItem[] Child products
     */
    public array $child_products = [];
    /**
     * @var string|null Common part of the name for group products
     */
    public ?string $group_mask = null;
    /**
     * @var float|null Minimum sale price of the product
     */
    public ?float $new_map_price = null;
    /**
     * @var int|null Number of units in stock
     */
    public ?int $r_avail = null;
    /**
     * @var string|null Unique product ID " sku "
     */
    public ?string $mpn = null;
    /**
     * @var string|null Message about the required time for order processing
     */
    public ?string $lead_time_message = null;
    /**
     * @var array|null Product Characteristics
     */
    public ?array $attributes = null;
    /**
     * @var array Files for the product: instructions, etc.
     */
    public array $product_files = [];
    /**
     * @var array Product options: size, color, etc.
     */
    public array $options = [];
    /**
     * @var array Product Video
     */
    public array $videos = [];

    /**
     * FeedItem constructor.
     * @param ParserInterface|null $parser
     */
    public function __construct( ParserInterface $parser = null )
    {
        if ( $parser ) {
            $parser->beforeParse();
            try {
                $this->setMpn( $parser->getMpn() );
                $this->setASIN( $parser->getASIN() );
                $this->setProductCode( $parser->getProductCode() );
                $this->setProduct( $parser->getProduct() ?: $parser::DEFAULT_PRODUCT_NAME );
                $this->setFulldescr( $parser->getDescription() );
                $this->setShortdescr( $parser->getShortDescription() );
                $this->setBrandName( $parser->getBrand() );
                $this->setListPrice( $parser->getListPrice() );
                $this->setCostToUs( $parser->getCostToUs() );
                $this->setNewMapPrice( $parser->getMinimumPrice() );
                $this->setUpc( $parser->getUpc() );
                $this->setImages( $parser->getImages() );
                $this->setMinAmount( $parser->getMinAmount() ?? $this->getMinAmount() );
                $this->setMultOrderQuantity( $parser->getMultOrderQuantity() ?? $this->getMultOrderQuantity() );
                $this->setCategories( $parser->getCategories() );
                $this->setSupplierInternalId( $parser->getInternalId() );
                $this->setBrandNormalized( $parser->getBrandNormalized() );
                $this->setWeight( $parser->getWeight() );
                $this->setShippingWeight( $parser->getShippingWeight() );
                $this->setDimX( $parser->getDimX() );
                $this->setDimY( $parser->getDimY() );
                $this->setDimZ( $parser->getDimZ() );
                $this->setShippingDimX( $parser->getShippingDimX() );
                $this->setShippingDimY( $parser->getShippingDimY() );
                $this->setShippingDimZ( $parser->getShippingDimZ() );
                $this->setEtaDateMmDdYyyy( $parser->getEtaDate() );
                $this->setLeadTimeMessage( $parser->getLeadTimeMessage() );
                $this->setAttributes( $parser->getAttributes() );
                $this->setOptions( $parser->getOptions() );
                $this->setVideos( $parser->getVideos() );
                $this->setProductFiles( $parser->getProductFiles() );

                if ( $parser->isGroup() ) {
                    $children = $parser->getChildProducts( $this );

                    $this->setMpn( '' );
                    $this->setIsGroup( true );
                    $this->setListPrice( null );
                    $this->setCostToUs( 0 );
                    $this->setNewMapPrice();
                    $this->setRAvail( 0 );
                    $this->setForsale( 'Y' );
                    $this->setImages( [] );

                    $children = array_reduce( $children, function ( $c, FeedItem $child ) use ( $parser ) {
                            if ( !isset( $c[ $child->getMpn() ] ) ) {
                                $c[ $child->getMpn() ] = $child;
                                $child->setGroupMask( $this->getProduct() );
                                $child->setMultOrderQuantity( $child->getMinAmount() > 1 ? 'Y' : 'N' );
                                $child->setBrandName( $child->getBrandName() ?: $parser->getVendor()->getSupplierName() );
                                $child->setProductCode( strtoupper( $parser->getVendor()->getPrefix() . $child->getMpn() ) );
                                $child->setSupplierInternalId( $parser->getInternalId() );
                                $child->setHashProduct();
                            }

                            return $c;
                        } ) ?? [];

                    $this->setChildProducts( array_values( $children ) );
                } else {
                    $this->setIsGroup( false );
                    $this->setRAvail( $parser->getAvail() );
                    $this->setForsale( $parser->getForsale() );
                    $this->setChildProducts( [] );
                }

                $this->setMultOrderQuantity( $parser->getMinAmount() > 1 ? 'Y' : 'N' );
                $this->setHashProduct();

                $parser->afterParse( $this );
            } catch ( Throwable $e ) {
                $message = '  ERROR: failed parse product' . PHP_EOL;
                $message .= 'message: ' . $e->getMessage() . PHP_EOL;
                $message .= '     in: ' . $e->getFile() . '(' . $e->getLine() . ')' . PHP_EOL;

                if ( $parser instanceof HtmlParser ) {
                    $message .= '    uri: ' . $parser->getUri() . PHP_EOL;
                }

                $stack = $e->getTraceAsString();

                // reduce stack to Parser class errors
                if ( preg_match( "/.*\\\\Parser->.*?\(\)/s", $stack, $matches ) ) {
                    $stack = array_map( static fn( $elem ) => trim( $elem ), explode( "\n", $matches[ 0 ] ) );

                    foreach ( $stack as $i => $line ) {
                        $prefix = ( $i === 0 ) ? '  stack: ' : '         ';
                        $message .= $prefix . $line . PHP_EOL;
                    }

                    $message .= PHP_EOL;
                }
                echo $message;
            }
        }
    }

    /**
     * @param string $productcode Sets the product code
     */
    public function setProductCode( string $productcode ): void
    {
        $this->productcode = (string)mb_strtoupper( str_replace( ' ', '-', StringHelper::removeSpaces( StringHelper::mb_trim( $productcode ) ) ) );
    }

    /**
     * @return string Returns the product code
     */
    public function getProductcode(): string
    {
        return $this->productcode;
    }

    /**
     * @param string|null $ASIN Sets the product ID in Amazon
     */
    public function setASIN( string $ASIN = null ): void
    {
        $this->ASIN = $ASIN;
    }

    /**
     * @return string|null Returns the product ID in Amazon
     */
    public function getASIN(): ?string
    {
        return $this->ASIN;
    }

    /**
     * @param string $product Sets the product name
     */
    public function setProduct( string $product ): void
    {
        $this->product = StringHelper::mb_ucwords( mb_strtolower( StringHelper::mb_trim( $product ) ) );
    }

    /**
     * @return string Returns the product name
     */
    public function getProduct(): string
    {
        return $this->product;
    }

    /**
     * @param float $cost_to_us Sets the price of the product with a discount
     */
    public function setCostToUs( float $cost_to_us ): void
    {
        $this->cost_to_us = round( StringHelper::mb_trim( $cost_to_us ), 2 );
    }

    /**
     * @return float Returns the cost of the product with a discount
     */
    public function getCostToUs(): float
    {
        return $this->cost_to_us;
    }

    /**
     * @param null|float $list_price Sets the original price of the product
     */
    public function setListPrice( ?float $list_price ): void
    {
        $this->list_price = null;
        if ( $list_price ) {
            $this->list_price = round( $list_price, 2 );
        }
    }

    /**
     * @return null|float Returns the original price of the product
     */
    public function getListPrice(): ?float
    {
        return $this->list_price;
    }

    /**
     * @param array $descr Sets the key features of the product
     */
    public function setShortdescr( array $descr = [] ): void
    {
        $descr = FeedHelper::normalizeShortDesc( $descr );
        if ( $descr ) {
            $this->descr = '<ul><li>' . html_entity_decode( implode( '</li><li>', $descr ) ) . '</li></ul>';
        }
    }

    /**
     * @return null|string Returns the key features of the product
     */
    public function getShortdescr(): ?string
    {
        return $this->descr;
    }

    /**
     * @param string $fulldescr Sets the product description
     */
    public function setFulldescr( string $fulldescr ): void
    {
        $this->fulldescr = StringHelper::mb_trim( StringHelper::cutTags( html_entity_decode( $fulldescr ) ) );
    }

    /**
     * @return string Returns the product description
     */
    public function getFulldescr(): string
    {
        return $this->fulldescr;
    }

    /**
     * @param string|null $brand_name Sets the product brand
     */
    public function setBrandName( ?string $brand_name ): void
    {
        $this->brand_name = StringHelper::mb_ucwords( mb_strtolower( StringHelper::mb_trim( $brand_name ) ) );
    }

    /**
     * @return string|null Returns the product brand
     */
    public function getBrandName(): ?string
    {
        return $this->brand_name;
    }

    /**
     * @param bool $brand_normalized
     */
    public function setBrandNormalized( bool $brand_normalized ): void
    {
        $this->brand_normalized = $brand_normalized;
    }

    /**
     * @return bool
     */
    public function getBrandNormalized(): bool
    {
        return $this->brand_normalized;
    }

    /**
     * @param string $forsale Sets whether the product can be sold
     */
    public function setForsale( string $forsale ): void
    {
        $this->forsale = $forsale;
    }

    /**
     * @return string Returns whether the product can be sold
     */
    public function getForsale(): string
    {
        return $this->forsale;
    }

    /**
     * @param DateTime|null $eta Sets the date of receipt of the goods in the warehouse
     */
    public function setEtaDateMmDdYyyy( DateTime $eta = null ): void
    {
        if ( $eta ) {
            $this->eta_date_mm_dd_yyyy = $eta->format( 'm/d/Y' );
        }
    }

    /**
     * @return DateTime|null Returns the date of receipt of the goods in the warehouse
     */
    public function getEtaDateMmDdYyyy(): ?DateTime
    {
        $date = null;
        if ( $this->eta_date_mm_dd_yyyy ) {
            $date = DateTime::createFromFormat( 'm/d/Y', $this->eta_date_mm_dd_yyyy, new DateTimeZone( 'EST' ) );
        }
        return $date ?: null;
    }

    /**
     * @param string|null $upc Sets the barcode of the product
     */
    public function setUpc( ?string $upc ): void
    {
        if ( $upc !== null ) {
            $this->upc = StringHelper::calculateUPC( StringHelper::mb_trim( $upc ) );
        }
    }

    /**
     * @return string|null Returns the barcode of the product
     */
    public function getUpc(): ?string
    {
        return $this->upc;
    }

    /**
     * @param string $supplier_internal_id Sets the link to the product page
     */
    public function setSupplierInternalId( string $supplier_internal_id ): void
    {
        $this->supplier_internal_id = $supplier_internal_id;
    }

    /**
     * @return string Returns a link to the product page
     */
    public function getSupplierInternalId(): string
    {
        return $this->supplier_internal_id;
    }

    /**
     * @param array $images Sets links to product images
     */
    public function setImages( array $images ): void
    {
        $this->images = $images;
    }

    /**
     * @return array Returns links to product images
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param array $alt_names Sets alternative image names
     */
    public function setAltNames( array $alt_names ): void
    {
        $this->alt_names = $alt_names;
    }

    /**
     * @param array Returns alternative image names
     */
    public function getAltNames(): array
    {
        return $this->alt_names;
    }

    /**
     * @param float|null $dim_x Sets the product size by " X "
     */
    public function setDimX( float $dim_x = null ): void
    {
        $this->dim_x = StringHelper::normalizeFloat( $dim_x );
    }

    /**
     * @param float|null Returns the size of the product by " X "
     */
    public function getDimX(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $dim_y Sets the product size by " Y "
     */
    public function setDimY( float $dim_y = null ): void
    {
        $this->dim_y = StringHelper::normalizeFloat( $dim_y );
    }

    /**
     * @param float|null Returns the size of the product by "Y"
     */
    public function getDimY(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $dim_z Sets the product size by " Z "
     */
    public function setDimZ( float $dim_z = null ): void
    {
        $this->dim_z = StringHelper::normalizeFloat( $dim_z );
    }

    /**
     * @param float|null Returns the size of the product by " Z "
     */
    public function getDimZ(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $weight Sets the weight of the product for delivery
     */
    public function setShippingWeight( float $weight = null ): void
    {
        $this->shipping_weight = StringHelper::normalizeFloat( $weight );
    }

    /**
     * @return float|null Returns the weight of the item for delivery
     */
    public function getShippingWeight(): ?float
    {
        return $this->shipping_weight;
    }

    /**
     * @param float|null $dim_x Sets the size of the product for delivery by " X "
     */
    public function setShippingDimX( float $dim_x = null ): void
    {
        $this->shipping_dim_x = StringHelper::normalizeFloat( $dim_x );
    }

    /**
     * @param float|null Returns the size of the product by " X "
     */
    public function getShippingDimX(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $dim_y Sets the size of the product for delivery by "Y"
     */
    public function setShippingDimY( float $dim_y = null ): void
    {
        $this->shipping_dim_y = StringHelper::normalizeFloat( $dim_y );
    }

    /**
     * @param float|null Returns the size of the product by "Y"
     */
    public function getShippingDimY(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $dim_z Sets the size of the product for delivery by " Z "
     */
    public function setShippingDimZ( float $dim_z = null ): void
    {
        $this->shipping_dim_z = StringHelper::normalizeFloat( $dim_z );
    }

    /**
     * @param float|null Returns the size of the product by " Z"
     */
    public function getShippingDimZ(): ?float
    {
        return $this->dim_x;
    }

    /**
     * @param float|null $weight Sets the weight of the product
     */
    public function setWeight( float $weight = null ): void
    {
        $this->weight = StringHelper::normalizeFloat( $weight );
    }

    /**
     * @return float|null Returns the weight of the product
     */
    public function getWeight(): ?float
    {
        return $this->weight;
    }

    /**
     * @param int $min_amount Sets the minimum purchase quantity of the product
     */
    public function setMinAmount( int $min_amount ): void
    {
        $this->min_amount = $min_amount;
    }

    /**
     * @return int Returns the minimum purchase quantity of the product
     */
    public function getMinAmount(): int
    {
        return $this->min_amount;
    }

    /**
     * @param string $mult_order_quantity Sets to sell the product by package or piece by piece
     */
    public function setMultOrderQuantity( string $mult_order_quantity ): void
    {
        $this->mult_order_quantity = $mult_order_quantity;
    }

    /**
     * @return string|null Returns to sell the product by package or piece by piece
     */
    public function getMultOrderQuantity(): ?string
    {
        return $this->mult_order_quantity;
    }

    /**
     * @param bool $is_group Sets whether the product is a group product
     */
    public function setIsGroup( bool $is_group ): void
    {
        $this->is_group = $is_group;
    }

    /**
     * @return bool Returns whether the product is a group product
     */
    public function isGroup(): bool
    {
        return $this->is_group;
    }

    /**
     * @param array $child_products Sets child products
     */
    public function setChildProducts( array $child_products ): void
    {
        $this->child_products = $child_products;
    }

    /**
     * @return array Returns child products
     */
    public function getChildProducts(): array
    {
        return $this->child_products;
    }

    /**
     * @param string|null $group_mask Sets the common part of the name for group products
     */
    public function setGroupMask( ?string $group_mask ): void
    {
        $this->group_mask = $group_mask;
    }

    /**
     * @return string|null Returns the common part of the name for group products
     */
    public function getGroupMask(): ?string
    {
        return $this->group_mask;
    }

    /**
     * @param float|null $new_map_price Sets the minimum selling price of the product
     */
    public function setNewMapPrice( float $new_map_price = null ): void
    {
        $this->new_map_price = $new_map_price ? round( $new_map_price, 2 ) : $new_map_price;
    }

    /**
     * @return float|null Returns the minimum selling price of the product
     */
    public function getNewMapPrice(): ?float
    {
        return $this->new_map_price;
    }

    /**
     * @param int|null $r_avail Sets the number of units in stock
     */
    public function setRAvail( ?int $r_avail = null ): void
    {
        $this->r_avail = $r_avail;
    }

    /**
     * @return int|null Returns the number of units in stock
     */
    public function getRAvail(): ?int
    {
        return $this->r_avail;
    }

    /**
     * @param string $mpn Sets the unique identifier of the product
     */
    public function setMpn( string $mpn ): void
    {
        $this->mpn = $mpn;
    }

    /**
     * @return string Returns a unique product ID
     */
    public function getMpn(): string
    {
        return $this->mpn;
    }

    /**
     * @param string|null $lead_time_message Sets a message about the required time for order processing
     */
    public function setLeadTimeMessage( ?string $lead_time_message ): void
    {
        $this->lead_time_message = $lead_time_message;
    }

    /**
     * @return string|null Returns a message about the required time to process the order
     */
    public function getLeadTimeMessage(): ?string
    {
        return $this->lead_time_message;
    }

    /**
     * @param array|null $getAttributes Sets the product characteristics
     */
    public function setAttributes( ?array $getAttributes ): void
    {
        $this->attributes = $getAttributes ? array_map( static fn( string $attribute ) => html_entity_decode( $attribute ), $getAttributes ) : $getAttributes;
    }

    /**
     * @param array|null Returns the product characteristics
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @param array $product_files Sets the files for the product
     */
    public function setProductFiles( array $product_files ): void
    {
        $this->product_files = $product_files;
    }

    /**
     * @return array Returns files to the product
     */
    public function getProductFiles(): array
    {
        return $this->product_files;
    }

    /**
     * @param array $options Sets the product options
     */
    public function setOptions( array $options ): void
    {
        $this->options = $options;
    }

    /**
     * @return array Returns product options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $videos Sets the video of the product
     */
    public function setVideos( array $videos ): void
    {
        $this->videos = $videos;
    }

    /**
     * @return array Returns a video of the product
     */
    public function getVideos(): array
    {
        return $this->videos;
    }

    /**
     * Sets the hash amount of the product
     * @throws Exception
     */
    public function setHashProduct(): void
    {
        $attrs = $this->propsToArray();
        unset( $attrs[ 'images' ] );
        $this->hash_product = md5( json_encode( $attrs, JSON_THROW_ON_ERROR ) );
    }

    /**
     * @return string Returns the hash amount of the product
     */
    public function getHashProduct(): string
    {
        return $this->hash_product;
    }

    /**
     * Cuts tags from the description, leaving the allowed tags, clearing them of styles, etc.
     *
     * @param $fulldescr
     * @param bool $flag
     * @param array $tags
     * @return null|string
     */
    public function cutTags( $fulldescr, $flag = true, array $tags = [] ): ?string
    {
        $mass = [
            'span',
            'p',
            'br',
            'ol',
            'ul',
            'li',
            'table',
            'thead',
            'tbody',
            'th',
            'tr',
            'td',
        ];

        $regexps = [
            '/<script[^>]*?>.*?<\/script>/i',
            '/<noscript[^>]*?>.*?<\/noscript>/i',
            '/<style[^>]*?>.*?<\/style>/i',
            '/<video[^>]*?>.*?<\/video>/i',
            '/<a[^>]*?>.*?<\/a>/i',
            '/<iframe[^>]*?>.*?<\/iframe>/i'
        ];
        foreach ( $regexps as $regexp ) {
            if ( preg_match( $regexp, $fulldescr ) ) {
                $fulldescr = preg_replace( $regexp, '', $fulldescr );
            }
        }

        $fulldescr = StringHelper::mb_trim( $fulldescr );

        if ( !$flag ) {
            $mass = [];
        }

        if ( !empty( $tags ) && is_array( $tags ) ) {
            foreach ( $tags as $tag ) {
                $regexp = '/<(\D+)\s?[^>]*?>/';
                if ( preg_match( $regexp, $tag, $matches ) ) {
                    $mass[] = $matches[ 1 ];
                } else {
                    $mass[] = $tag;
                }
            }
        }

        $tags_string = '';
        foreach ( $mass as $tag ) {
            $tags_string .= "<$tag>";
        }

        $fulldescr = strip_tags( $fulldescr, $tags_string );

        foreach ( $mass as $tag ) {

            $regexp = "/(<$tag)([^>]*)(>)/i";

            if ( preg_match( $regexp, $fulldescr ) ) {
                $fulldescr = preg_replace( $regexp, '$1$3', $fulldescr );
            }
        }
        return $fulldescr;
    }

    /**
     * Converts object properties to an array
     *
     * @param array $attr
     * @return array
     */
    public function propsToArray( $attr = [] ): array
    {
        $result = get_object_vars( $this );

        return !$attr ? $result : array_intersect_key( $result, array_flip( $attr ) );
    }

    /**
     * @return string Converts object properties to a json string
     * @throws Exception
     */
    public function propsToJson(): string
    {
        return json_encode( $this->propsToArray(), JSON_THROW_ON_ERROR );
    }

    /**
     * @param array $categories
     */
    public function setCategories( array $categories ): void
    {
        $this->supplier_categories = array_map( 'mb_strtolower', $categories );
        $this->supplier_categories = array_map( [ StringHelper::class, 'mb_ucfirst' ], $this->supplier_categories );
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        return $this->supplier_categories;
    }
}

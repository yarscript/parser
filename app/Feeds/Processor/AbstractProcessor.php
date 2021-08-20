<?php

namespace App\Feeds\Processor;

use App\Feeds\Downloader\FtpDownloader;
use App\Feeds\Downloader\HttpDownloader;
use App\Feeds\Feed\FeedItem;
use App\Feeds\Feed\FeedValidate;
use App\Feeds\Parser\ParserInterface;
use App\Feeds\Parser\XLSNParser;
use App\Feeds\Storage\AbstractFeedStorage;
use App\Feeds\Storage\FileStorage;
use App\Feeds\Utils\Collection;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Repositories\DxRepositoryInterface;
use DateTime;
use Exception;
use Ms48\LaravelConsoleProgressBar\Facades\ConsoleProgressBar;

/**
 * Hooks
 * @method processInit
 * @method afterProcessItem
 * @method beforeProcess
 * @method afterProcess
 * @method afterFeedItemMerge( FeedItem $fi )
 */
abstract class AbstractProcessor
{
    /**
     * Specifies the numbers of the sheets that the pry-parser should process
     * Used if the price list contains several sheets with the necessary information
     *
     * To do this, you need to specify an array of numbers, for example [0, 1, 2] in which each key is the ID of a sheet in the table,
     * and the value of each key is the parser identifier with the name of the form Price<number from the array>Parser
     */
    public const PRICE_ACTIVE_SHEET = [ 0 ];
    public const PRICE_ACTIVE_MULTIPLE_SHEET = [];
    /**
     * Specifies the numbers of files sorted in alphabetical order that the pry-parser should process
     * Used if you need to process several price lists
     *
     * Similarly to PRICE_ACTIVE_SHEET, you must specify an array where the keys are the identifiers of the price lists,
     * and the value of each key is the parser ID
     */
    public const PRICE_ACTIVE_FILES = [ 0 ];
    public const FEED_TYPE_INVENTORY = 'inventory';
    public const FEED_TYPE_PRODUCT = 'product';
    public const FEED_SOURCE_PRICE = 'price';
    public const FEED_SOURCE_SITE = 'site';
    public const DX_ID = null;
    public const DX_NAME = null;
    public const DX_PREFIX = null;
    public const DX_SOURCE = null;
    /**
     * An array of css selectors that select elements of links (<a>) to product categories for their further traversal
     */
    public const CATEGORY_LINK_CSS_SELECTORS = [];
    /**
     * An array of css selectors that select link elements (<a>) to product pages to collect information from them
     */
    public const PRODUCT_LINK_CSS_SELECTORS = [];
    /**
     * Determines the number of links that will be processed per request
     */
    protected const CHUNK_SIZE = 20;
    /**
     * Defines the waiting time for processing the request in seconds
     */
    protected const REQUEST_TIMEOUT_S = 60;
    /**
     * Determines the delay between sending requests in seconds
     */
    protected const DELAY_S = 0;
    /**
     * Determines whether to use a static user agent or change it with each request
     */
    protected const STATIC_USER_AGENT = false;
    /**
     * Determines whether to use a proxy or not
     */
    protected const USE_PROXY = false;

    private const E_PARSER_NOT_FOUND = 'Class %s does not exists';

    public ?AbstractFeedStorage $storage;
    /**
     * @var HttpDownloader|FtpDownloader
     */
    protected HttpDownloader|FtpDownloader $downloader;
    /**
     * @var FeedItem[] Array of app/Feeds/Feed/FeedItem objects containing information about products taken from the price list
     */
    public array $price_items = [];
    /**
     * @var FeedItem[] Array of app/Feeds/Feed/FeedItem objects containing information about products taken from the site
     */
    public array $feed_items = [];
    /**
     * @var array An array of product urls that the parser must process in order not to parse the entire site
     * Used only for testing the parser's performance
     * Before sending the parser to production, you must delete this property
     */
    public array $custom_products = [];
    public array $dx_info = [];
    /**
     * @var array Parameters for authorization
     *
     * 'check_login_text' = > 'Log Out' - A verification word that is displayed only to authorized users (Log Out, My account, and others)
     * 'auth_url' = > 'https://www.authorise_uri.com/login' - The URL to which the authorization request is sent
     * 'auth_form_url' = > 'https://www.authorise_uri.com/login' - The URL of the page where the authorization form is located
     * 'auth_info' = > [] - An array of parameters for authorization, contains all the fields that were sent by the browser for authorization
     * 'find_fields_form' = > true|false-Specifies to search for additional fields of the authorization form before sending the request
     * If this parameter is omitted, the system will consider its value as "true"
     * 'api_auth' = > true|false - Specifies in which form to send the authorization form parameters ("request_payload" or "form_data")
     * If this parameter is omitted, the system will consider its value as "false".
     * By default, the parameters are sent as normal form fields
     *
     * Example of the auth_info content:
     * 'auth_info' => [
     *     'login[username]' => 'user@my-email.com',
     *     'login[password]' => 'My-Password',
     * ],
     */
    protected array $params = [
        'check_login_text' => '',
        'auth_url' => '',
        'auth_form_url' => '',
        'auth_info' => [],
    ];
    protected array $first = [];
    protected array $headers = [];
    /**
     * @var int|null the number of products that the parser must collect in order not to parse the entire site
     * Used only for testing the parser's performance
     * Before sending the parser to production, you must delete this property
     */
    protected ?int $max_products = null;
    protected Collection $process_queue;

    public function __construct(
        string $code = null,
        DxRepositoryInterface $dxRepo = null,
        AbstractFeedStorage $storage = null
    ) {
        if ( $code && $dxRepo ) {
            //multi-feeds for different stores
            $codeSplit = explode( '__', $code );
            // Replacing the Dx _ prefix with -
            $code = str_replace( '_', '-', $codeSplit[ 0 ] );
            $this->dx_info = $dxRepo->get( $code, $codeSplit[ 1 ] ?? null );
        }
        if ( $storage ) {
            $this->storage = $storage;
        }
        $this->process_queue = app( Collection::class );
    }

    public function __call( $name, $arguments )
    {
    }

    /**
     * check for dev mode
     * @return bool
     */
    public function isDevMode(): bool
    {
        return config( 'env', 'production' ) === 'dev';
    }

    public function getFeedType(): string
    {
        return !empty( $this->dx_info[ 'feeds' ] ) ? array_values( $this->dx_info[ 'feeds' ] )[ 0 ][ 'type' ] : self::FEED_TYPE_PRODUCT;
    }

    public function getFeedSource(): string
    {
        return self::FEED_SOURCE_SITE;
    }

    public function getFeedDate(): DateTime
    {
        return new DateTime();
    }

    public function getQueue(): Collection
    {
        return $this->process_queue;
    }

    public function getDownloader(): HttpDownloader|FtpDownloader
    {
        return $this->downloader;
    }

    /**
     * Returns all links to category pages that were found by the selectors specified in the constant "CATEGORY_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getCategoriesLinks( Data $data, string $url ): array
    {
        return static::CATEGORY_LINK_CSS_SELECTORS
            ? ( new ParserCrawler( $data->getData(), $url ) )
                ->filter( implode( ', ', static::CATEGORY_LINK_CSS_SELECTORS ) )
                ->each( static fn( ParserCrawler $node ) => new Link( static::getNormalizedCategoryLink( $node->link()->getUri() ) ) )
            : [];
    }

    /**
     * Returns all links to product pages that were found by the selectors specified in the constant "PRODUCT_LINK_CSS_SELECTORS"
     * @param Data $data Html markup of the loaded page
     * @param string $url the url of the loaded page
     * @return array An array of links containing app/Feeds/Utils/Link objects
     */
    public function getProductsLinks( Data $data, string $url ): array
    {
        $links = static::PRODUCT_LINK_CSS_SELECTORS
            ? ( new ParserCrawler( $data->getData(), $url ) )
                ->filter( implode( ', ', static::PRODUCT_LINK_CSS_SELECTORS ) )
                ->each( static fn( ParserCrawler $node ) => new Link( static::getNormalizedLink( $node->link()->getUri() ) ) )
            : [];
        return array_values( array_filter( $links ?? [], [ $this, 'filterProductLinks' ] ) );
    }

    final public function getSupplierId(): int
    {
        return $this->dx_info[ 'id' ] ?? static::DX_ID;
    }

    final public function getSupplierName(): string
    {
        return $this->dx_info[ 'name' ] ?? static::DX_NAME;
    }

    final public function getPrefix(): string
    {
        return $this->dx_info[ 'prefix' ] ?? static::DX_PREFIX;
    }

    final public function getSource(): string
    {
        return static::DX_SOURCE ?? $this->dx_info[ 'source' ] ?? '';
    }

    /**
     * Returns the parser object
     * @param string $prefix
     * @return ParserInterface
     */
    public function getParser( string $prefix = '' ): ParserInterface
    {
        $class = substr( static::class, 0, strrpos( static::class, '\\' ) + 1 ) . $prefix . 'Parser';
        if ( class_exists( $class ) ) {
            return new $class( $this );
        }

        die( sprintf( self::E_PARSER_NOT_FOUND, $class ) );
    }

    public static function getNormalizedCategoryLink( string $link ): string
    {
        return $link;
    }

    public static function getNormalizedLink( string $link ): string
    {
        return $link;
    }

    /**
     * Returns the price parser object
     * @param array $sheet
     * @param null $price
     * @return ParserInterface
     */
    public function getPriceParser( array $sheet, $price = null ): ParserInterface
    {
        $parser = 'Price';
        if ( !is_null( $price ) && key( $sheet ) && count( static::PRICE_ACTIVE_MULTIPLE_SHEET ) ) {
            $parser .= $price ? "{$price}_" . key( $sheet ) : '_' . key( $sheet );
        } else if ( $price ) {
            $parser .= $price;
        } else {
            $parser .= key( $sheet ) ?: '';
        }
        return $this->getParser( $parser );
    }

    /**
     * Collects information about the product from the page
     * @param Data $data html content of the product page
     * @param string $url the url of the current product page
     * @return FeedItem[]
     */
    protected function getFeedItems( Data $data, string $url ): array
    {
        return $this->getParser()->parseContent( $data, [ 'url' => $url ] );
    }

    /**
     * @param int $max_products Sets the maximum number of products that the parser must collect in order not to parse the entire site
     * It is used only for testing the functionality of the parser
     */
    public function setMaxProducts( int $max_products ): void
    {
        $this->max_products = $max_products;
    }

    /**
     * Loads the links passed in the array
     * @param Link[] $links
     * @return Data[]
     */
    protected function fetchLinks( array $links ): array
    {
        return $this->getDownloader()->fetch( $links );
    }

    /**
     * Collects links to product categories and pages
     * @param $crawler
     * @param string $url
     */
    protected function parseLinks( $crawler, string $url ): void
    {
        $this->process_queue->addLinks( $this->getCategoriesLinks( $crawler, $url ), Collection::LINK_TYPE_CATEGORY );
        $this->process_queue->addLinks( $this->getProductsLinks( $crawler, $url ), Collection::LINK_TYPE_PRODUCT );

        $num_product_links = $this->process_queue->where( 'type', Collection::LINK_TYPE_PRODUCT )->count();

        if (
            $this->max_products
            && $num_product_links >= $this->max_products
            && $this->isDevMode()
        ) {
            $categories = $this->process_queue->where( 'type', Collection::LINK_TYPE_CATEGORY )->all();
            array_walk( $categories, static fn( array $item ) => $item[ 'link' ]->setVisited() );
        }
    }

    /**
     * Merges products taken from the price list and from the website
     * The products taken from the site have a lower priority, so the information from the price list will replace the information from the site
     * Except for a few fields
     * @param FeedItem[] $feed_target
     * @param FeedItem[] $feed_source
     */
    public function mergeFeeds( array $feed_target, array $feed_source ): void
    {
        foreach ( $feed_target as $fi_target ) {
            if ( $fi_target->isGroup() ) {
                $this->mergeFeeds( $fi_target->getChildProducts(), $feed_source );
            }

            if (
                isset( $feed_source[ $fi_target->getMpn() ] )
                && $fi_source = $feed_source[ $fi_target->getMpn() ]
            ) {
                foreach ( get_object_vars( $fi_source ) as $name => $source_value ) {
                    if ( !empty( $source_value ) ) {
                        switch ( $name ) {
                            case 'product':
                            case 'fulldescr':
                                if ( $fi_target->$name !== XLSNParser::DUMMY_PRODUCT_NAME ) {
                                    continue 2;
                                }
                                break;
                            case 'min_amount':
                                if ( $fi_target->$name !== 1 ) {
                                    continue 2;
                                }
                                break;
                            case 'forsale':
                                if ( $fi_target->$name !== 'Y' ) {
                                    continue 2;
                                }
                                break;
                        }
                        $fi_target->$name = $source_value;
                    }
                }
            }

            $this->afterFeedItemMerge( $fi_target );
        }
    }

    /**
     * Used to remove invalid products from the feed before saving
     * @param FeedItem $fi
     * @return bool
     */
    protected function isValidFeedItem( FeedItem $fi ): bool
    {
        return true;
    }

    public function processError( $e, $key = null ): void
    {

    }

    /**
     * loading errors handler hook
     */
    protected function loadExceptionHandler( Exception $e ): void
    {
        print PHP_EOL . $e->getMessage() . PHP_EOL;
    }

    /**
     * Crawls category pages and collects information about products
     */
    final public function process(): void
    {
        $this->processInit();

        $this->beforeProcess();

        $total = $this->process_queue->count();

        if ( $this->custom_products && $this->isDevMode() ) {
            $this->process_queue = $this->process_queue->clear();
            $this->process_queue->addLinks( $this->custom_products, Collection::LINK_TYPE_PRODUCT );
        }

        while ( $links = $this->process_queue->next( null, static::CHUNK_SIZE ) ) {
            try {
                $new_links = $this->fetchLinks( $links );
            } catch ( Exception $e ) {
                $this->loadExceptionHandler( $e );
                sleep( 1 );
                continue;
            }

            foreach ( $new_links as $current_link => $data ) {
                if ( $data ) {
                    switch ( $this->process_queue->get( $current_link )[ 'type' ] ) {
                        case Collection::LINK_TYPE_CATEGORY:
                            try {
                                $this->parseLinks( $data, $current_link );
                            } catch ( Exception $e ) {
                                echo PHP_EOL . 'Loading error: ' . $current_link . PHP_EOL . $e->getMessage() . PHP_EOL;
                                continue 2;
                            }
                            break;
                        case Collection::LINK_TYPE_PRODUCT:
                            $feed_item = $this->getFeedItems( $data, $current_link );
                            if ( $this->storage instanceof FileStorage ) {
                                $this->feed_items += $feed_item;
                            } else {
                                $this->mergeFeeds( $feed_item, $this->price_items );
                                $fi = array_shift( $feed_item );
                                if ( $this->isValidFeedItem( $fi ) ) {
                                    $this->storage->saveFeed( $this, [ $fi ] );
                                }
                            }
                            break;
                    }

                    if (
                        $this->max_products
                        && count( $this->feed_items ) >= $this->max_products
                        && $this->isDevMode()
                    ) {
                        break 2;
                    }
                }

                $this->afterProcessItem();

                $total = $this->process_queue->count();
                ConsoleProgressBar::showProgress( 1, $total );
            }

            array_walk( $links, static fn( Link $link ) => $link->setVisited( true ) );

            if ( $this->max_products && $this->isDevMode() && count( $this->feed_items ) >= $this->max_products ) {
                break;
            }
        }

        $this->afterProcess();

        if ( $total ) {
            ConsoleProgressBar::showProgress( $total, $total );
        }

        if ( $this->feed_items ) {
            $this->mergeFeeds( $this->feed_items, $this->price_items );
            foreach ( $this->feed_items as $key => $fi ) {
                if ( !$this->isValidFeedItem( $fi ) ) {
                    unset( $this->feed_items[ $key ] );
                }
            }

            if ( $this->isDevMode() ) {
                new FeedValidate( $this->feed_items, $this->dx_info );
            }

            $this->storage->saveFeed( $this, $this->feed_items );
        }
        $this->storage->shutdown();
    }
}

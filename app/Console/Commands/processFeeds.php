<?php

namespace App\Console\Commands;

use App\Feeds\Processor\AbstractProcessor;
use App\Feeds\Storage\AbstractFeedStorage;
use App\Feeds\Storage\FileStorage;
use App\Feeds\Storage\RabbitStorage;
use App\Repositories\DxRepository;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class processFeeds extends Command
{
    private const VENDOR_ROOT_PATTERN = 'App\\Feeds\\Vendors\\%s\\Vendor';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed {vendor} {dev=dev} {storage=file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process feed';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws Exception
     */
    public function handle(): void
    {
        if ( $this->argument( 'dev' ) === 'dev' ) {
            Config::set( 'env', 'dev' );
        }

        $processor = self::getVendor( $this->argument( 'vendor' ), $this->argument( 'storage' ) );

        print "\nStart feed {$this->argument('vendor')}\n";

        $processor->process();

        print "\nDONE!\n";
    }

    private static function getVendor( string $vendor, string $storage ): AbstractProcessor
    {
        $class = sprintf( self::VENDOR_ROOT_PATTERN, $vendorCode = strtoupper( $vendor ) );
        if ( class_exists( $class ) ) {
            return new $class( $vendorCode, app( DxRepository::class ), self::getStorage( $storage ) );
        }

        throw new Exception( "Class {$class} does not exists" );
    }

    private static function getStorage( string $storage ): AbstractFeedStorage
    {
        $storage_path = match ( $storage ) {
            'rabbit' => RabbitStorage::class,
            default => FileStorage::class,
        };
        return app( $storage_path );
    }
}

<?php

namespace App\Console;

use App\Feeds\Processor\AbstractProcessor;
use App\Repositories\DxRepository;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule( Schedule $schedule )
    {
        $this->vendorsSchedule( $schedule );
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load( __DIR__ . '/Commands' );

        require base_path( 'routes/console.php' );
    }

    protected function vendorsSchedule( Schedule $schedule ): void
    {
        /** @var AbstractProcessor $command */

        $vendors = app( DxRepository::class )->schedule();
        $storagePath = Storage::disk( 'log' )->getDriver()->getAdapter()->getPathPrefix();

        foreach ( $vendors as $vendor ) {
            $schedule
                ->command( "feed {$vendor}" )
                ->withoutOverlapping( 60 * 60 * 24 * 2 )
                ->sendOutputTo( $storagePath . $vendor . '.log' )
                ->timezone( 'EST' );
        }
    }
}

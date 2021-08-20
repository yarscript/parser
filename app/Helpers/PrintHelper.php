<?php

namespace App\Helpers;

class PrintHelper
{
    public static function printf( $f, ...$params ): void
    {
        echo PHP_EOL . sprintf( $f, ...$params ) . PHP_EOL;
    }

    public static function printfError( $f, ...$params ): void
    {
        echo PHP_EOL . 'ERROR';
        self::printf( $f, ...$params );
    }
}

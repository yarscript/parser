<?php

namespace App\Feeds\Parser;

class CsvParser extends TxtParser
{
    public function getRows( $content ): ?array
    {
        $rows = [];
        $temp = fopen( 'php://temp', 'rb+' );
        fwrite( $temp, $content );
        rewind( $temp );

        while ( $csvRow = fgetcsv( $temp ) ) {
            $rows[] = $csvRow;
        }

        fclose( $temp );

        $this->buildMapHeaders( $rows );

        array_shift( $rows );

        return $rows;
    }

    public function parseRow( $row ): array
    {
        return $row;
    }
}

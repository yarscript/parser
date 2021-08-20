<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Traits\ParserTrait;
use App\Feeds\Utils\Data;
use App\Helpers\StringHelper;
use Ms48\LaravelConsoleProgressBar\Facades\ConsoleProgressBar;

abstract class TxtParser implements ParserInterface
{
    use ParserTrait;

    protected string $column_delimiter = '';
    // name-letter table columns map
    protected array $map = [];

    public function getRows( $content ): ?array
    {
        $rows = explode( "\n", $content );
        $this->buildMapHeaders( $rows );
        return $rows;
    }

    /**
     * @param Data $data
     * @param array $params
     * @return array
     */
    public function parseContent( Data $data, array $params = [] ): array
    {
        $items = [];

        $rows = $this->getRows( $data->getData() );
        $total = count( $rows );

        /** @var string|string[] $row */
        foreach ( $rows as $row ) {
            $this->data = $this->parseRow( StringHelper::mb_trim( $row ) );
            $parser = clone $this;
            $item = new FeedItem( $parser );

            $mpn = $item->isGroup() ? md5( microtime() . mt_rand() ) : $item->mpn;
            $items[ $mpn ] = $item;
            ConsoleProgressBar::showProgress( 1, $total );
        }

        return $items;
    }

    public function parseRow( $row ): array
    {
        if ( $this->column_delimiter ) {
            return explode( $this->column_delimiter, $row );
        }
        return [ $row ];
    }

    protected function buildMapHeaders( array $rows ): int
    {
        $headerRows = 0;
        foreach ( $rows as $row ) {
            $not_empty_row = false;

            foreach ( $this->parseRow( $row ) as $key => $cell ) {
                // new column name
                if ( $value = trim( $cell ) ) {
                    $this->map[ $value ][] = $key;
                    $not_empty_row = true;
                }
                else {
                    $this->map[ $key ][] = $key;
                }
            }

            $headerRows++;

            $this->map = $not_empty_row ? $this->map : [];

            if ( $this->isValidHeaders() ) {
                break;
            }
        }
        return $headerRows;
    }

    /**
     * if the price list is incorrect in terms of titles, redefine this function
     */
    protected function isValidHeaders(): bool
    {
        return count( $this->map ) > 0;
    }

    protected function getValueByColumnName( string $field, $index = 0 ): string
    {
        $column_name = trim( $field );

        if ( !isset ( $this->map[ $column_name ] ) ) {
            return '';
        }

        $column_idx = $this->map[ $column_name ][ $index ] ?? $this->map[ $column_name ][ 0 ];

        return trim( $this->data[ $column_idx ] ?? '' );
    }
}

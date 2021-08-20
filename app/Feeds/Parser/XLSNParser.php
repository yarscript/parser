<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Traits\ParserTrait;
use App\Feeds\Utils\Data;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Storage;
use Ms48\LaravelConsoleProgressBar\Facades\ConsoleProgressBar;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;
use Throwable;

class XLSNParser implements ParserInterface
{
    use ParserTrait;

    /**
     * the current row in the parsing cycle of the table file
     * @property Row row
     */
    protected Row $row;
    public const DUMMY_PRODUCT_NAME = 'Dummy';
    protected const MAX_LINES_READ = 150000;

    // name-letter table columns map
    protected array $map = [];

    protected static function getSpreadSheet( $file ): Spreadsheet
    {
        return IOFactory::load( $file );
    }

    /**
     * build headers map
     * @param RowIterator $row_iterator
     * @return int
     */
    protected function buildMapHeaders( RowIterator $row_iterator ): int
    {
        $headerRows = 0;
        foreach ( $row_iterator as $row ) {
            $not_empty_row = false;

            foreach ( $row->getCellIterator() as $key => $cell ) {
                // new column name
                if ( $value = trim( $cell->getValue() ) ) {
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

    // чтение .xlsx файла
    public function getLines( $content, $sheet ): array
    {
        $fName = uniqid( mt_rand(), true ) . '.xlsx';
        Storage::disk( 'temp' )->put( $fName, $content );

        $items = [];
        $storagePath = Storage::disk( 'temp' )->getDriver()->getAdapter()->getPathPrefix();

        $spreadsheet = static::getSpreadSheet( $storagePath . $fName );
        try {
            $worksheet = $spreadsheet->getSheet( $sheet );

            $row_iterator = $worksheet->getRowIterator();

            $head_rows = $this->buildMapHeaders( $row_iterator );

            $count_rows = min( $worksheet->getHighestRow(), static::MAX_LINES_READ );

            // get lines
            foreach ( $row_iterator as $this->row ) {
                $parser = clone $this;

                if ( $row_iterator->key() <= $head_rows ) {
                    continue;
                }
                $i = new FeedItem( $parser );
                $mpn = $i->isGroup() ? md5( microtime() . mt_rand() ) : $i->mpn;
                if ( !isset( $items[ $mpn ] ) ) {
                    $items[ $mpn ] = $i;
                }
                ConsoleProgressBar::showProgress( 1, $count_rows );
                if ( $row_iterator->key() >= $count_rows ) {
                    break;
                }
            }

            $worksheet->__destruct();
        } catch ( Throwable $e ) {
            echo 'Xlsx parse error: ' . $e->getMessage() . PHP_EOL;
        } finally {
            $spreadsheet->__destruct();
        }

        Storage::disk( 'temp' )->delete( $fName );
        return $items;
    }

    /**
     * if the price list is incorrect in terms of titles, redefine this function
     */
    protected function isValidHeaders(): bool
    {
        return count( $this->map ) > 0;
    }

    /**
     * parsing xml/xlsx and building a list of products based on them(feed list)
     *
     * @param Data $data
     * @param array $params
     * @return FeedItem[]
     */
    public function parseContent( Data $data, array $params = [] ): array
    {
        return $this->getLines( (string)$data, $params[ 'sheet' ] ?? 0 );
    }

    /**
     * get the value of the $column column in the row $this->row
     * @param $column
     * @return string
     * @deprecated
     */
    protected function getValue( $column ): string
    {
        if ( $w = $this->row->getWorksheet() ) {
            try {
                if ( $cell = $w->getCell( $column . $this->row->getRowIndex() ) ) {
                    $res = StringHelper::mb_trim( $cell->getDataType() === DataType::TYPE_FORMULA ? $cell->getCalculatedValue() : $cell->getValue() );
                }
            } catch ( Throwable $e ) {
                $res = '';
            }
        }
        return $res ?? '';
    }

    protected function getValueByColumnName( $column_name, $index = 0 ): string
    {
        $column_name = trim( $column_name );

        if ( !isset ( $this->map[ $column_name ] ) ) {
            $column_key = array_filter( array_map( fn( $array ) => in_array( $column_name, $array, true ) ? key( $this->map ) : 0, $this->map ) );
            if ( $column_key ) {
                $column_name = array_key_first( $column_key );
            }
            else {
                return '';
            }
        }

        $column_letter = $this->map[ $column_name ][ $index ] ?? $this->map[ $column_name ][ 0 ];

        return $this->getValue( $column_letter );
    }
}

<?php

namespace App\Helpers;

use App\Feeds\Utils\ParserCrawler;

class FeedHelper
{
    /**
     * Clears empty elements from the array of product features
     * @param array $short_desc Product Features
     * @return array Enhanced Features
     */
    public static function normalizeShortDesc( array $short_desc ): array
    {
        $short_desc = array_map( static fn( $desc ) => StringHelper::normalizeSpaceInString( $desc ), $short_desc );
        return array_filter( $short_desc, static fn( $desc ) => str_replace( ' ', '', $desc ) );
    }

    public static function removePriceInDesc( string $desc ): string
    {
        if ( $desc ) {
            $crawler = new ParserCrawler( $desc );
            $children = $crawler->filter( 'body' )->children();
            foreach ( $children as $child ) {
                if ( StringHelper::existsMoney( $child->textContent ) ) {
                    $desc = str_replace( $child->ownerDocument->saveHTML( $child ), '', $desc );
                }
            }
        }
        return $desc;
    }

    /**
     * Gets the dimensions of the product from the line
     * @param string $string A string containing the dimensions
     * @param string $separator Separator, which is used to convert a string into an array with the dimensions of the product
     * @param int $x_index Size index in the array on the X axis
     * @param int $y_index Size index in the array on the Y axis
     * @param int $z_index Size index in the array on the Z axis
     * @return array Array containing the dimensions of the product
     */
    public static function getDimsInString( string $string, string $separator, int $x_index = 0, int $y_index = 1, int $z_index = 2 ): array
    {
        $raw_dims = explode( $separator, $string );

        $dims[ 'x' ] = isset( $raw_dims[ $x_index ] ) ? StringHelper::getFloat( $raw_dims[ $x_index ] ) : null;
        $dims[ 'y' ] = isset( $raw_dims[ $y_index ] ) ? StringHelper::getFloat( $raw_dims[ $y_index ] ) : null;
        $dims[ 'z' ] = isset( $raw_dims[ $z_index ] ) ? StringHelper::getFloat( $raw_dims[ $z_index ] ) : null;

        return $dims;
    }
    
    /**
    * Gets the product dimensions from a string using regular expressions
    * @param string $string A string containing the dimensions
    * @param array $regexes Array of regular expressions for substring search
    * @param int $x_index Product length index
    * @param int $y_index Product height index
    * @param int $z_index Product width index
    * @return null[] An array containing the dimensions of the product
    */
    public static function getDimsRegexp( string $string, array $regexes, int $x_index = 1, int $y_index = 2, int $z_index = 3 ): array
    {
        $dims = [
            'x' => null,
            'y' => null,
            'z' => null
        ];

        foreach ( $regexes as $regex ) {
            if ( preg_match( $regex, $string, $matches ) ) {
                $dims[ 'x' ] = isset( $matches[ $x_index ] ) ? StringHelper::getFloat( $matches[ $x_index ] ) : null;
                $dims[ 'y' ] = isset( $matches[ $y_index ] ) ? StringHelper::getFloat( $matches[ $y_index ] ) : null;
                $dims[ 'z' ] = isset( $matches[ $z_index ] ) ? StringHelper::getFloat( $matches[ $z_index ] ) : null;
            }
        }

        return $dims;
    }

    /**
     * Converts weight from grams to pounds
     * @param float|null $g_value Weight in grams
     * @return float|null
     */
    public static function convertLbsFromG( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.0022 );
    }

    /**
     * Converts weight from an ounce to pounds
     * @param float|null $g_value Weight in ounces
     * @return float|null
     */
    public static function convertLbsFromOz( ?float $g_value ): ?float
    {
        return self::convert( $g_value, 0.063 );
    }

    /**
     * Converts a number from an arbitrary unit of measurement to an arbitrary unit of measurement
     * @param float|null $value The value of the unit of measurement to be translated
     * @param float $contain_value The value of one unit of measurement relative to another
     * @return float|null
     */
    public static function convert( ?float $value, float $contain_value ): ?float
    {
        return StringHelper::normalizeFloat( $value * $contain_value );
    }
    
    /**
     * Searches for product features and characteristics in the description using regular expressions
     * @param string $description Product Description
     * @param array $user_regexes Array of regular expressions
     * @param array $short_desc Array of product features
     * @param array $attributes Array of product characteristics
     * @return array Returns an array containing
     *  [
     *     'description' => string - product description cleared of features and characteristics
     *     'short_desc' = > array - array of product features
     *     'attributes' => array|null - an array of product characteristics
     *  ]
     */
    public static function getShortsAndAttributesInDesc( string $description, array $user_regexes = [], array $short_desc = [], array $attributes = [] ): array
    {
        $description = StringHelper::cutTagsAttributes( $description );

        $regex_header_list_spec = '<(div>)?(p>)?(span>)?(b>)?(strong>)?Specifications:(\s+)?(<\/div)?(<\/p)?(<\/span)?(<\/b)?(<\/strong)?>(\s+)?(<\/\w+>)+?(\s+)?';
        $regex_header_list_feat = '<(div>)?(p>)?(span>)?(b>)?(strong>)?Features:(\s+)?(<\/div)?(<\/p)?(<\/span)?(<\/b)?(<\/strong)?>(\s+)?(<\/\w+>)+?(\s+)?';
        $regex_content_list = '(\s+)?(<ul>)?(\s+)?(?<content_list><li>.*<\/li>)(\s+)?(<\/ul>)?';

        $regexes[] = "/$regex_header_list_spec$regex_content_list/is";
        $regexes[] = "/$regex_header_list_feat$regex_content_list/is";
        $regexes = array_merge( $regexes, $user_regexes );

        foreach ( $regexes as $regex ) {
            if ( preg_match_all( $regex, $description, $match ) && isset( $match[ 'content_list' ] ) ) {
                foreach ( $match[ 'content_list' ] as $content_list ) {
                    $crawler = new ParserCrawler( $content_list );
                    $crawler->filter( 'li' )->each( static function ( ParserCrawler $c ) use ( &$short_desc, &$attributes ) {
                        $text = $c->text();
                        if ( str_contains( $text, ':' ) ) {
                            [ $key, $value ] = explode( ':', $text, 2 );
                            $attributes[ trim( $key ) ] = trim( StringHelper::normalizeSpaceInString( $value ) );
                        }
                        else {
                            $short_desc[] = $text;
                        }
                    } );
                }
                $description = (string)preg_replace( $regex, '', $description );
            }
        }

        return [
            'description' => $description,
            'short_desc' => array_values( array_filter( $short_desc, static fn( string $attribute ) => !empty( str_replace( ' ', '', $attribute ) ) ) ),
            'attributes' => array_filter( $attributes ) ?: null
        ];
    }
}

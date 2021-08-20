<?php

namespace App\Feeds\Utils;

use App\Helpers\HttpHelper;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class Proxy
{
    /**
     * @return string Returns a valid proxy address to use
     * @throws Exception
     */
    public static function getProxy(): string
    {
        $proxy_invalid = null;
        $proxies = self::getProxyCheckerNetProxies();

        if ( $proxies ) {
            while ( !$proxy_invalid ) {
                $current_proxy = $proxies[ $proxy_index = random_int( 0, count( $proxies ) - 1 ) ];

                try {
                    $proxy_invalid = true;
                } catch ( Exception $e ) {
                    print "Error: {$e->getMessage()}\n";
                    unset( $proxies[ $proxy_index ] );
                }
            }
            return $current_proxy ?? '';
        }
        return '';
    }

    /**
     * Gets a list of available proxies via the API and puts them in the cache
     * @return array An array of available valid proxies
     */
    private static function getProxyCheckerNetProxies(): array
    {
        if ( $validProxies = Cache::get( 'proxies' ) ) {
            return $validProxies;
        }

        $validProxies = [];
        $total = 0;

        while ( !$total ) {
            $date = new DateTime( 'now' );

            $url = 'https://checkerproxy.net/api/archive/' . $date->format( 'Y-m-d' );
            if ( ( $response = self::fetchProxies( $url ) ) && $json = json_decode( $response, true ) ) {
                foreach ( $json as $items ) {
                    if ( (int)$items[ 'type' ] === 2 && HttpHelper::validateProxyIpPort( $items[ 'addr' ] ) ) {
                        $validProxies[] = $items[ 'addr' ];
                    }
                }
            }
            $total = count( $validProxies );
        }
        return Cache::remember( 'proxies', 6 * 60, function () use ( $validProxies ) {
            return $validProxies;
        } );
    }

    private static function fetchProxies( $url ): ?string
    {
        echo "Get proxies list $url \n";
        $c = new Client();
        return $c->get( $url )->getBody()->getContents();
    }
}
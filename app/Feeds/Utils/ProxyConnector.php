<?php

namespace App\Feeds\Utils;

use App\Feeds\Downloader\HttpDownloader;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class ProxyConnector
{
    private const MAX_CONNECT_LIMIT = 50;

    public function connect( HttpDownloader $downloader, Link $link ): void
    {
        $connect = 0;
        while ( true ) {
            if ( $connect >= self::MAX_CONNECT_LIMIT ) {
                $downloader->setUseProxy( false );
                $downloader->getClient()->setProxy( null );
                break;
            }

            if ( $this->callConnect( $downloader, $link ) ) {
                break;
            }
            $connect++;
        }
    }

    private function callConnect( HttpDownloader $downloader, Link $link ): bool
    {
        $downloader->getClient()->setRequestTimeOut( 10 );

        $connection = false;
        $proxy = Proxy::getProxy();
        $downloader->getClient()->setProxy( $proxy );

        $response = null;

        $promise = $downloader->getClient()->request( $link->getUrl(), $link->getParams(), $link->getMethod(), $link->getTypeParams() )->then(
            function ( Response $res ) use ( &$response ) {
                $response = $res;
            },
            function ( RequestException $exc ) use ( &$exception ) {
                $exception = $exc;
            }
        );
        $promise->wait();

        if ( $response ) {
            $connection = true;
            print PHP_EOL . "Use proxy: $proxy" . PHP_EOL;
        }
        $downloader->getClient()->setRequestTimeOut( $downloader->timeout_s );
        return $connection;
    }
}

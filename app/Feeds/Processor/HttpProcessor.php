<?php

namespace App\Feeds\Processor;

use App\Feeds\Downloader\HttpDownloader;
use App\Feeds\Utils\Collection;
use App\Feeds\Utils\Link;

abstract class HttpProcessor extends AbstractProcessor
{
    public function processInit(): void
    {
        $this->downloader = new HttpDownloader(
            $this->headers,
            $this->params,
            static::REQUEST_TIMEOUT_S,
            static::DELAY_S,
            static::STATIC_USER_AGENT,
            static::USE_PROXY,
            $this->getSource()
        );

        if ( $this->first ) {
            $this->process_queue->addLinks( array_map( static fn( $url ) => new Link( $url ), $this->first ), Collection::LINK_TYPE_CATEGORY );
        }
    }

    /**
     * Clears the array of links that do not lead to product pages
     * Used in app/Feeds/Processor/AbstractProcessor::getProductLinks
     * @param Link $link
     * @return bool
     */
    public function filterProductLinks( Link $link ): bool
    {
        return (bool)$link->getUrl();
    }
}

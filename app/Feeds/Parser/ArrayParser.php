<?php

namespace App\Feeds\Parser;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Traits\ParserTrait;
use App\Feeds\Utils\Data;

class ArrayParser implements ParserInterface
{
    use ParserTrait;

    public function parseContent( Data $data, array $params=[]): array
    {
        foreach ( $this->getArray($data) as $row ) {
            $this->data = $row;

            $item = new FeedItem($this);

            if (!$item->getProduct()) {
                echo "\n Error: Product name not found\n";
                continue;
            }
            $mpn = $item->isGroup() ? md5(microtime().mt_rand()): $item->mpn;
            $items[$mpn] = $item;
        }
        return $items ?? [];
    }

    protected function getArray( Data $data ): array
    {
        return $data->getJSON();
    }
}

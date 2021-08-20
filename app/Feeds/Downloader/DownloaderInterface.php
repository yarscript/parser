<?php

namespace App\Feeds\Downloader;

use App\Feeds\Utils\Data;

interface DownloaderInterface
{
    public function get($url, $params = [], $files = []): Data;
}

<?php

namespace App\Helpers;


class UrlHelper
{
    public static function getLastPart($url): string
    {
        $url = rtrim($url,'/');
        return substr($url, strrpos($url, '/') + 1);
    }

    /** Return url with last part
     * @param $url
     * @return string|null
     */
    public static function Shortify($url): ?string
    {
        if (!$url) {
            return null;
        }

        return self::getBaseUrl($url) . '/' . self::getLastPart($url);

    }

    public static function getBaseUrl($url): string
    {
        $result = parse_url($url);
        return "{$result['scheme']}://{$result['host']}";
    }

    public static function getPath($url): string
    {
        $result = parse_url($url);
        return $result['path'];
    }
}

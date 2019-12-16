<?php

namespace App\Service;

class MirrorUrlFilter
{
    /**
     * @param string $url
     * @return string|null
     */
    public function filter(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        /** @var string[] $parsedUrl */
        $parsedUrl = parse_url($url);

        if (
            empty($parsedUrl['scheme'])
            || empty($parsedUrl['host'])
            || !in_array($parsedUrl['scheme'], ['http', 'https', 'ftp'])
            || !empty($parsedUrl['port'])
            || !empty($parsedUrl['user'])
            || !empty($parsedUrl['pass'])
            || substr_count($parsedUrl['host'], '.') < 1
            || preg_match('/^[0-9\.]+$/', $parsedUrl['host'])
            || preg_match('/^\[[0-9a-f:]+\]$/', $parsedUrl['host'])
            || preg_match(
                '/(?:^|\.)(?:localhost|local|box|lan|home|onion|internal|intranet|private)$/',
                $parsedUrl['host']
            )
        ) {
            return null;
        }

        if (empty($parsedUrl['path'])) {
            $parsedUrl['path'] = '/';
        }
        $parsedUrl['path'] = preg_replace(
            [
                '#^(.+?)(?:extra|core)/(?:os/)?.*#',
                '#^(.+?)pkgstats-[0-9\.]+-[0-9]+-.+?\.pkg\.tar\.(?:g|x)z$#'
            ],
            '$1',
            $parsedUrl['path']
        );
        $parsedUrl['path'] = str_replace(['//', '\\'], ['/', ''], (string)$parsedUrl['path']);

        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];
    }
}

<?php

namespace App\Tests\Service;

use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\TestCase;

class MirrorUrlFilterTest extends TestCase
{
    /**
     * @dataProvider provideUrls
     */
    public function testFilter(string $input, ?string $expected): void
    {
        $mirrorUrlFilter = new MirrorUrlFilter();
        $this->assertEquals($expected, $mirrorUrlFilter->filter($input));
    }

    public function provideUrls(): array
    {
        return [
            ['https://mirror.archlinux.de/', 'https://mirror.archlinux.de/'],
            ['https://mirror.archlinux.de', 'https://mirror.archlinux.de/'],
            ['https://mirror.archlinux.de//', 'https://mirror.archlinux.de/'],
            ['https://mirror.archlinux.de/\\', 'https://mirror.archlinux.de/'],
            ['', null],
            ['file:///mnt/mirror/', null],
            ['https://192.168.0.1/', null],
            ['https://[::1]/', null],
            ['https://localhost/', null],
            ['https://foo.localhost/', null],
            ['https://foo.local/', null],
            ['https://foo.box/', null],
            ['https://foo.lan/', null],
            ['https://foo.home/', null],
            ['https://myhost/', null],
            ['https://some.tld:8080/', null],
            ['http://mirror.archlinux.de/extra/os/i686/pkgstats-2.3-8-i686.pkg.tar.xz', 'http://mirror.archlinux.de/'],
            ['http://mirror.archlinux.de/core/os/i686/pkgstats-2.3-8-i686.pkg.tar.xz', 'http://mirror.archlinux.de/'],
            ['http://mirror.archlinux.de/extra/i686/', 'http://mirror.archlinux.de/'],
            ['http://mirror.archlinux.de/core/i686/', 'http://mirror.archlinux.de/'],
            ['http://mirror.archlinux.de/i686/extra/', 'http://mirror.archlinux.de/i686/'],
            [
                'http://mirror.archlinux.de/pub/archlinux/pkgstats-2.3-6-any.pkg.tar.xz',
                'http://mirror.archlinux.de/pub/archlinux/'
            ],
            ['https://foo:bar@mirror.archlinux.de/', null],
            ['http://mirror.archlinux.de/pkgstats-3.11-1-x86_64.pkg.tar.zst', 'http://mirror.archlinux.de/']
        ];
    }
}

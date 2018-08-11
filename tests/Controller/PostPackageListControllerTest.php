<?php

namespace App\Tests\Controller;

use App\Tests\Util\DatabaseTestCase;

/**
 * @covers \App\Controller\PostPackageListController
 */
class PostPackageListControllerTest extends DatabaseTestCase
{
    /**
     * @param string $version
     * @dataProvider provideSupportedVersions
     */
    public function testPostPackageListIsSuccessful(string $version)
    {
        $client = $this->getClient();

        $client->request(
            'POST',
            '/post',
            ['pkgstatsver' => $version, 'arch' => 'x86_64', 'packages' => 'pkgstats', 'modules' => 'snd']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains('Thanks for your submission. :-)', $client->getResponse()->getContent());
    }

    /**
     * @return array
     */
    public function provideSupportedVersions(): array
    {
        return [
            ['1.0'],
            ['2.0'],
            ['2.1'],
            ['2.2'],
            ['2.3']
        ];
    }

    /**
     * @return array
     */
    public function provideUnsupportedVersions(): array
    {
        return [
            ['0.1'],
            [''],
            ['a'],
            ['1.0alpha'],
            ['42']
        ];
    }

    /**
     * @param string $version
     * @dataProvider provideUnsupportedVersions
     */
    public function testUnsupportedVersionFails(string $version)
    {
        $client = $this->getClient();

        $client->request(
            'POST',
            '/post',
            ['pkgstatsver' => $version, 'arch' => 'x86_64', 'packages' => 'pkgstats', 'modules' => 'snd']
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $architecture
     * @dataProvider provideSupportedArchitectures
     */
    public function testPostPackageListWithArchitectureIsSuccessful(string $architecture)
    {
        $client = $this->getClient();

        $client->request(
            'POST',
            '/post',
            ['pkgstatsver' => '2.3', 'arch' => $architecture, 'packages' => 'pkgstats', 'modules' => 'snd']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    /**
     * @return array
     */
    public function provideSupportedArchitectures(): array
    {
        return [
            ['i686'],
            ['x86_64']
        ];
    }

    /**
     * @return array
     */
    public function provideUnsupportedArchitectures(): array
    {
        return [
            [''],
            ['arm']
        ];
    }
}

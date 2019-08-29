<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Entity\User;
use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use SymfonyDatabaseTest\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @covers \App\Controller\PostPackageListController
 */
class PostPackageListControllerTest extends DatabaseTestCase
{
    public function testPostPackageListIsSuccessful()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'cpuarch' => 'x86_64',
                'packages' => 'pkgstats',
                'mirror' => 'https://mirror.archlinux.de/'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('UTF-8', $client->getResponse()->getCharset());
        $this->assertStringContainsString('text/plain', (string)$client->getResponse()->headers->get('Content-Type'));
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertStringContainsString('Thanks for your submission. :-)', $client->getResponse()->getContent());

        /** @var UserRepository $userRepository */
        $userRepository = $this->getEntityManager()->getRepository(User::class);
        $this->assertCount(1, $userRepository->findAll());
        /** @var User $user */
        $user = $userRepository->findAll()[0];
        $this->assertEquals('x86_64', $user->getArch());
        $this->assertEquals('x86_64', $user->getCpuarch());
        $this->assertEquals('https://mirror.archlinux.de/', $user->getMirror());
        $this->assertEquals(1, $user->getPackages());
        $this->assertNull($user->getCountrycode());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        $this->assertCount(1, $packageRepository->findAll());
        /** @var Package $package */
        $package = $packageRepository->findAll()[0];
        $this->assertEquals('pkgstats', $package->getPkgname());
        $this->assertEquals(1, $package->getCount());
    }

    /**
     * @param string $version
     * @return KernelBrowser
     */
    private function createPkgstatsClient(string $version = '2.4'): KernelBrowser
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_USER_AGENT', sprintf('pkgstats/%s', $version));
        return $client;
    }

    /**
     * @param string $version
     * @dataProvider provideSupportedVserions
     */
    public function testSupportedVersions(string $version)
    {
        $client = $this->createPkgstatsClient($version);

        $client->request(
            'POST',
            '/post',
            ['arch' => 'x86_64', 'packages' => 'pkgstats']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    /**
     * @return array
     */
    public function provideSupportedVserions(): array
    {
        return [
            ['2.3'],
            ['2.4'],
            ['2.4.0'],
            ['2.4.2'],
            ['2.4.9999']
        ];
    }

    /**
     * @return array
     */
    public function provideUnsupportedVersions(): array
    {
        return [
            ['1.0'],
            ['2.0'],
            ['2.1'],
            ['2.2'],
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
        $client = $this->createPkgstatsClient($version);

        $client->request(
            'POST',
            '/post',
            ['arch' => 'x86_64', 'packages' => 'pkgstats']
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $architecture
     * @dataProvider provideSupportedArchitectures
     */
    public function testPostPackageListWithArchitectureIsSuccessful(string $architecture)
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            ['arch' => $architecture, 'packages' => 'pkgstats']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLocalMirrorGetsIgnored()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            ['arch' => 'x86_64', 'packages' => 'pkgstats', 'mirror' => 'file://mirror/']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLongMirrorGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => 'pkgstats',
                'mirror' => 'https://' . str_repeat('a', 255) . '.com/'
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $architecture
     * @dataProvider provideUnsupportedArchitectures
     */
    public function testPostPackageListWithUnsupportedArchitectureFails(string $architecture)
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            ['arch' => $architecture, 'packages' => 'pkgstats']
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $architecture
     * @dataProvider provideUnsupportedCpuArchitectures
     */
    public function testPostPackageListWithUnsupportedCpuArchitectureFails(string $architecture)
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'cpuarch' => $architecture,
                'packages' => 'pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testEmptyPackageListGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => ''
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLongPackageListGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => (function () {
                    $result = '';
                    for ($i = 0; $i < 10002; $i++) {
                        $result .= 'package-' . $i . "\n";
                    }
                    return $result;
                })()
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testInvalidPackageListGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => '-pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLongPackageGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => str_repeat('a', 256)
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testQuietMode()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => 'pkgstats',
                'quiet' => 'true'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertEquals('', $client->getResponse()->getContent());
    }

    public function testSubmissionsAreLimitedPerUser()
    {
        $client = $this->createPkgstatsClient();

        for ($i = 1; $i <= 11; $i++) {
            $client->request(
                'POST',
                '/post',
                ['arch' => 'x86_64', 'packages' => 'pkgstats']
            );
            if ($i <= 10) {
                $this->assertTrue($client->getResponse()->isSuccessful());
            } else {
                $this->assertTrue($client->getResponse()->isForbidden());
            }
        }
    }

    /**
     * @return array
     */
    public function provideSupportedArchitectures(): array
    {
        return [
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
            ['i686'],
            ['arm']
        ];
    }

    /**
     * @return array
     */
    public function provideUnsupportedCpuArchitectures(): array
    {
        return [
            ['i686'],
            ['arm']
        ];
    }
}

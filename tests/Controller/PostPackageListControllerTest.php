<?php

namespace App\Tests\Controller;

use App\Entity\Module;
use App\Entity\Package;
use App\Entity\User;
use App\Repository\ModuleRepository;
use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use App\Tests\Util\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

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
                'modules' => 'snd',
                'mirror' => 'http://localhost'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains('Thanks for your submission. :-)', $client->getResponse()->getContent());

        /** @var UserRepository $userRepository */
        $userRepository = $this->getEntityManager()->getRepository(User::class);
        $this->assertCount(1, $userRepository->findAll());
        /** @var User $user */
        $user = $userRepository->findAll()[0];
        $this->assertEquals('x86_64', $user->getArch());
        $this->assertEquals('x86_64', $user->getCpuarch());
        $this->assertEquals('http://localhost', $user->getMirror());
        $this->assertEquals(1, $user->getPackages());
        $this->assertEquals(1, $user->getModules());
        $this->assertNull($user->getCountrycode());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        $this->assertCount(1, $packageRepository->findAll());
        /** @var Package $package */
        $package = $packageRepository->findAll()[0];
        $this->assertEquals('pkgstats', $package->getPkgname());
        $this->assertEquals(1, $package->getCount());

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = $this->getEntityManager()->getRepository(Module::class);
        $this->assertCount(1, $moduleRepository->findAll());
        /** @var Module $module */
        $module = $moduleRepository->findAll()[0];
        $this->assertEquals('snd', $module->getName());
        $this->assertEquals(1, $module->getCount());
    }

    /**
     * @param string $version
     * @return Client
     */
    private function createPkgstatsClient(string $version = '2.3'): Client
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_USER_AGENT', sprintf('pkgstats/%s', $version));
        return $client;
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
            ['arch' => 'x86_64', 'packages' => 'pkgstats', 'modules' => 'snd']
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
            ['arch' => $architecture, 'packages' => 'pkgstats', 'modules' => 'snd']
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
                'mirror' => 'https://' . str_repeat('a', 255)
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
            ['arch' => $architecture, 'packages' => 'pkgstats', 'modules' => 'snd']
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
                'packages' => 'pkgstats',
                'modules' => 'snd'
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

    public function testLongModuleListGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => 'pkgstats',
                'modules' => (function () {
                    $result = '';
                    for ($i = 0; $i < 10002; $i++) {
                        $result .= 'module-' . $i . "\n";
                    }
                    return $result;
                })()
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLongModuleGetsRejected()
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => 'x86_64',
                'packages' => 'pkgstats',
                'modules' => str_repeat('a', 256)
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
                'modules' => 'snd',
                'quiet' => 'true'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertEquals('', $client->getResponse()->getContent());
    }

    public function testSubmissionsAreLimitedPerUser()
    {
        for ($i = 1; $i <= 11; $i++) {
            $client = $this->createPkgstatsClient();
            $client->request(
                'POST',
                '/post',
                ['arch' => 'x86_64', 'packages' => 'pkgstats', 'modules' => 'snd']
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

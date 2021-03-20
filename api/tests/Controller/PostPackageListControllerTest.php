<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Entity\User;
use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\PostPackageListController
 */
class PostPackageListControllerTest extends DatabaseTestCase
{
    public function testSubmitPackageListIsSuccessful(): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/api/submit',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
            (string)json_encode(
                [
                    'version' => '3',
                    'system' => [
                        'architecture' => 'x86_64'
                    ],
                    'os' => [
                        'architecture' => 'x86_64'
                    ],
                    'pacman' => [
                        'mirror' => 'https://mirror.archlinux.de/',
                        'packages' => ['pkgstats', 'pacman']
                    ]
                ]
            )
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
        $this->assertEmpty($client->getResponse()->getContent());

        /** @var UserRepository $userRepository */
        $userRepository = $this->getEntityManager()->getRepository(User::class);
        $this->assertCount(1, $userRepository->findAll());
        /** @var User $user */
        $user = $userRepository->findAll()[0];
        $this->assertEquals('x86_64', $user->getArch());
        $this->assertEquals('x86_64', $user->getCpuarch());
        $this->assertEquals('https://mirror.archlinux.de/', $user->getMirror());
        $this->assertNull($user->getCountrycode());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        /** @var Package[] $packages */
        $packages = $packageRepository->findAll();
        $this->assertCount(2, $packages);
        $packagesArray = array_map(fn($package) => $package->getName(), $packages);
        $this->assertTrue(in_array('pkgstats', $packagesArray));
        $this->assertTrue(in_array('pacman', $packagesArray));
    }

    public function testPostPackageListIsSuccessful(): void
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
        $this->assertNull($user->getCountrycode());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        $this->assertCount(1, $packageRepository->findAll());
        /** @var Package $package */
        $package = $packageRepository->findAll()[0];
        $this->assertEquals('pkgstats', $package->getName());
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
        $client->setServerParameter('CONTENT_TYPE', 'application/x-www-form-urlencoded');
        $client->setServerParameter('HTTP_ACCEPT', 'text/plain');
        return $client;
    }

    /**
     * @param string $version
     * @dataProvider provideSupportedVserions
     */
    public function testSupportedVersions(string $version): void
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
            ['2.4.9999'],
            ['2.4.2-5-g163d6c2'],
            ['2.5.0'],
            ['3'],
            ['3.0.0']
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
    public function testUnsupportedVersionFails(string $version): void
    {
        $client = $this->createPkgstatsClient($version);

        $client->request(
            'POST',
            '/post',
            ['arch' => 'x86_64', 'packages' => 'pkgstats']
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLocalMirrorGetsIgnored(): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            ['arch' => 'x86_64', 'packages' => 'pkgstats', 'mirror' => 'file://mirror/']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLongMirrorGetsRejected(): void
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
     * @param string $arch
     * @param string $cpuArch
     * @dataProvider provideUnsupportedArchitectures
     */
    public function testPostPackageListWithUnsupportedArchitectureFails(string $arch, string $cpuArch): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => $arch,
                'cpuarch' => $cpuArch,
                'packages' => 'pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $cpuArch
     * @param string $arch
     * @dataProvider provideUnsupportedCpuArchitectures
     */
    public function testPostPackageListWithUnsupportedCpuArchitectureFails(string $cpuArch, string $arch): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => $arch,
                'cpuarch' => $cpuArch,
                'packages' => 'pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    /**
     * @param string $cpuArch
     * @param string $arch
     * @dataProvider provideSupportedCpuArchitectures
     */
    public function testPostPackageListWithSupportedCpuArchitectureIsSuccessful(string $cpuArch, string $arch): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => $arch,
                'cpuarch' => $cpuArch,
                'packages' => 'pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    /**
     * @param string $arch
     * @param string $cpuArch
     * @dataProvider provideSupportedArchitectures
     */
    public function testPostPackageListWithSupportedArchitectureIsSuccessful(string $arch, string $cpuArch): void
    {
        $client = $this->createPkgstatsClient();

        $client->request(
            'POST',
            '/post',
            [
                'arch' => $arch,
                'cpuarch' => $cpuArch,
                'packages' => 'pkgstats'
            ]
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testEmptyPackageListGetsRejected(): void
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

    public function testLongPackageListGetsRejected(): void
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

    public function testInvalidPackageListGetsRejected(): void
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

    public function testLongPackageGetsRejected(): void
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

    public function testQuietMode(): void
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

    public function testSubmissionsAreLimitedPerUser(): void
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
                $this->assertEquals(429, $client->getResponse()->getStatusCode());
            }
        }
    }

    public function testPostPackageListIncrementsPackageCount(): void
    {
        $this->getEntityManager()->persist(
            (new Package())
                ->setName('pkgstats')
                ->setMonth((int)date('Ym'))
        );

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

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        $this->assertCount(1, $packageRepository->findAll());
        /** @var Package $package */
        $package = $packageRepository->findAll()[0];
        $this->assertEquals('pkgstats', $package->getName());
        $this->assertEquals(2, $package->getCount());
    }

    /**
     * @return array
     */
    public function provideSupportedArchitectures(): array
    {
        $result = [];
        $entries = [
            // arch -> cpuArch
            ['x86_64', ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4']],
            ['i686', ['i686', 'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4']],
            ['arm', ['aarch64', 'armv5', 'armv6', 'armv7']],
            ['armv6h', ['aarch64', 'armv6', 'armv7']],
            ['armv7h', ['aarch64', 'armv7']],
            ['aarch64', ['aarch64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $cpuArch) {
                $result[] = [$entry[0], $cpuArch];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function provideSupportedCpuArchitectures(): array
    {
        $result = [];
        $entries = [
            // cpuArch -> arch
            ['x86_64', ['x86_64', 'i686']],
            ['x86_64_v2', ['x86_64', 'i686']],
            ['x86_64_v3', ['x86_64', 'i686']],
            ['x86_64_v4', ['x86_64', 'i686']],
            ['i686', ['i686']],
            ['aarch64', ['aarch64', 'armv7h', 'armv6h', 'arm']],
            ['armv5', ['arm']],
            ['armv6', ['armv6h', 'arm']],
            ['armv7', ['armv7h', 'armv6h', 'arm']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function provideUnsupportedArchitectures(): array
    {
        $result = [];
        $entries = [
            // arch -> cpuArch
            ['', ['']],
            ['ppc', ['ppc']],
            ['i486', ['i486']],
            ['x86_64', ['i686']],
            ['aarch64', ['x86_64']],
            ['aarch64', ['armv5']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $cpuArch) {
                $result[] = [$entry[0], $cpuArch];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function provideUnsupportedCpuArchitectures(): array
    {
        $result = [];
        $entries = [
            // cpuArch -> arch
            ['', ['']],
            ['ppc', ['ppc']],
            ['i486', ['i486']],
            ['aarch64', ['x86_64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }
}

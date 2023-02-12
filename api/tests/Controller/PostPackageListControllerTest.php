<?php

namespace App\Tests\Controller;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Repository\PackageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->sendRequest($client);

        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
        $this->assertEmpty($client->getResponse()->getContent());

        $countryRepository = $this->getEntityManager()->getRepository(Country::class);
        $this->assertCount(1, $countryRepository->findAll());

        $mirrorRepository = $this->getEntityManager()->getRepository(Mirror::class);
        $mirrors = $mirrorRepository->findAll();
        $this->assertCount(1, $mirrors);
        $this->assertInstanceOf(Mirror::class, $mirrors[0]);
        $this->assertEquals('https://mirror.archlinux.de/', $mirrors[0]->getUrl());

        $operatingSystemArchitectureRepository = $this->getEntityManager()->getRepository(
            OperatingSystemArchitecture::class
        );
        $operatingSystemArchitectures = $operatingSystemArchitectureRepository->findAll();
        $this->assertCount(1, $operatingSystemArchitectures);
        $this->assertInstanceOf(OperatingSystemArchitecture::class, $operatingSystemArchitectures[0]);
        $this->assertEquals('x86_64', $operatingSystemArchitectures[0]->getName());

        $systemArchitectureRepository = $this->getEntityManager()->getRepository(SystemArchitecture::class);
        $systemArchitectures = $systemArchitectureRepository->findAll();
        $this->assertCount(1, $systemArchitectures);
        $this->assertInstanceOf(SystemArchitecture::class, $systemArchitectures[0]);
        $this->assertEquals('x86_64', $systemArchitectures[0]->getName());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        /** @var Package[] $packages */
        $packages = $packageRepository->findAll();
        $this->assertCount(2, $packages);
        $packagesArray = array_map(fn($package) => $package->getName(), $packages);
        $this->assertTrue(in_array('pkgstats', $packagesArray));
        $this->assertTrue(in_array('pacman', $packagesArray));
    }

    private function createPkgstatsClient(): KernelBrowser
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_USER_AGENT', 'pkgstats/3.2.2');
        $client->setServerParameter('CONTENT_TYPE', 'application/json');
        $client->setServerParameter('HTTP_ACCEPT', 'application/json');
        return $client;
    }

    private function sendRequest(
        KernelBrowser $client,
        string $systemArchitecture = 'x86_64',
        string $osArchitecture = 'x86_64',
        string $mirror = 'https://mirror.archlinux.de/',
        array $packages = ['pkgstats', 'pacman'],
        string $version = '3'
    ): void {
        $client->request(
            'POST',
            '/api/submit',
            [],
            [],
            [
                'REMOTE_ADDR' => '2a02:fb00::1',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ],
            (string)json_encode(
                [
                    'version' => $version,
                    'system' => [
                        'architecture' => $systemArchitecture
                    ],
                    'os' => [
                        'architecture' => $osArchitecture
                    ],
                    'pacman' => [
                        'mirror' => $mirror,
                        'packages' => $packages
                    ]
                ]
            )
        );
    }

    #[DataProvider('provideSupportedVserions')]
    public function testSupportedVersions(string $version): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, version: $version);
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public static function provideSupportedVserions(): array
    {
        return [
            ['3']
        ];
    }

    public static function provideUnsupportedVersions(): array
    {
        return [
            ['1.0'],
            ['2.0'],
            ['2.1'],
            ['2.2'],
            ['2.3'],
            ['2.4'],
            ['2.4.0'],
            ['2.4.2'],
            ['2.4.9999'],
            ['2.4.2-5-g163d6c2'],
            ['2.5.0'],
            ['3.0.0'],
            ['3.1'],
            ['3.2.0'],
            ['0.1'],
            [''],
            ['a'],
            ['1.0alpha'],
            ['42']
        ];
    }

    #[DataProvider('provideUnsupportedVersions')]
    public function testUnsupportedVersionFails(string $version): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, version: $version);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLocalMirrorGetsIgnored(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, mirror: 'file://mirror/');
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLongMirrorGetsRejected(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, mirror: 'https://' . str_repeat('a', 255) . '.com/');
        $this->assertTrue($client->getResponse()->isClientError());
    }

    #[DataProvider('provideUnsupportedArchitectures')]
    public function testPostPackageListWithUnsupportedArchitectureFails(string $arch, string $cpuArch): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, systemArchitecture: $cpuArch, osArchitecture: $arch);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    #[DataProvider('provideUnsupportedCpuArchitectures')]
    public function testPostPackageListWithUnsupportedCpuArchitectureFails(string $cpuArch, string $arch): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, systemArchitecture: $cpuArch, osArchitecture: $arch);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    #[DataProvider('provideSupportedCpuArchitectures')]
    public function testPostPackageListWithSupportedCpuArchitectureIsSuccessful(string $cpuArch, string $arch): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, systemArchitecture: $cpuArch, osArchitecture: $arch);
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    #[DataProvider('provideSupportedArchitectures')]
    public function testPostPackageListWithSupportedArchitectureIsSuccessful(string $arch, string $cpuArch): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, systemArchitecture: $cpuArch, osArchitecture: $arch);
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testEmptyPackageListGetsRejected(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, packages: []);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLongPackageListGetsRejected(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest(
            $client,
            packages: iterator_to_array(
                (function () {
                    for ($i = 0; $i < 10002; $i++) {
                        yield 'package-' . $i;
                    }
                })()
            )
        );

        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testInvalidPackageListGetsRejected(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, packages: ['-pkgstats']);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testLongPackageGetsRejected(): void
    {
        $client = $this->createPkgstatsClient();
        $this->sendRequest($client, packages: [str_repeat('a', 256)]);
        $this->assertTrue($client->getResponse()->isClientError());
    }

    public function testSubmissionsAreLimitedPerUser(): void
    {
        $client = $this->createPkgstatsClient();

        for ($i = 1; $i <= 11; $i++) {
            $this->sendRequest($client);
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
        $this->sendRequest($client, packages: ['pkgstats']);

        $this->assertTrue($client->getResponse()->isSuccessful());

        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->getEntityManager()->getRepository(Package::class);
        $this->assertCount(1, $packageRepository->findAll());
        /** @var Package $package */
        $package = $packageRepository->findAll()[0];
        $this->assertEquals('pkgstats', $package->getName());
        $this->assertEquals(2, $package->getCount());
    }

    public static function provideSupportedArchitectures(): array
    {
        $result = [];
        $entries = [
            // arch -> cpuArch
            ['x86_64', ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4']],
            ['i686', ['i686', 'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4']],
            ['arm', ['aarch64', 'armv5', 'armv6', 'armv7']],
            ['armv6h', ['aarch64', 'armv6', 'armv7']],
            ['armv7h', ['aarch64', 'armv7']],
            ['aarch64', ['aarch64']],
            ['riscv64', ['riscv64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $cpuArch) {
                $result[] = [$entry[0], $cpuArch];
            }
        }

        return $result;
    }

    public static function provideSupportedCpuArchitectures(): array
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
            ['armv7', ['armv7h', 'armv6h', 'arm']],
            ['riscv64', ['riscv64']],
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }

    public static function provideUnsupportedArchitectures(): array
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

    public static function provideUnsupportedCpuArchitectures(): array
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

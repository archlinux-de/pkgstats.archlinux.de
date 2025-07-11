<?php

namespace App\Tests\Controller;

use App\Controller\ApiPackagesController;
use App\Entity\Package;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversClass(ApiPackagesController::class)]
class ApiPackagesControllerTest extends DatabaseTestCase
{
    #[DataProvider('providePackageNames')]
    public function testFetchAllPackages(string $packageName): void
    {
        $entityManager = $this->getEntityManager();
        $package = new Package()
            ->setName($packageName)
            ->setMonth((int)new \DateTime()->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePopularityList($client->getResponse()->getContent());
    }

    private function assertAllowsCrossOriginAccess(Response $response): void
    {
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array{'total': int, 'count': int, 'packagePopularities': list<array{'name': string}>}
     */
    private function assertPackagePopularityList(string $json): array
    {
        $this->assertJson($json);

        /** @var array{'total': int, 'count': int, 'packagePopularities': list<array{'name': string}>} $packageList */
        $packageList = json_decode($json, true);
        $this->assertIsArray($packageList);
        $this->assertArrayHasKey('total', $packageList);
        $this->assertIsInt($packageList['total']);
        $this->assertArrayHasKey('count', $packageList);
        $this->assertIsInt($packageList['count']);
        $this->assertArrayHasKey('packagePopularities', $packageList);
        $this->assertIsArray($packageList['packagePopularities']);

        foreach ($packageList['packagePopularities'] as $package) {
            $this->assertPackagePopularity((string)json_encode($package));
        }

        return $packageList;
    }

    private function assertPackagePopularity(string $json): void
    {
        $this->assertJson($json);

        $package = json_decode($json, true);
        $this->assertIsArray($package);
        $this->assertArrayHasKey('name', $package);
        $this->assertIsString($package['name']);
        $this->assertArrayHasKey('samples', $package);
        $this->assertIsInt($package['samples']);
        $this->assertArrayHasKey('count', $package);
        $this->assertIsInt($package['count']);
        $this->assertArrayHasKey('popularity', $package);
        $this->assertIsNumeric($package['popularity']);
    }

    public function testFetchEmptyList(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePopularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyPackage(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages/foo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePopularity($client->getResponse()->getContent());
    }

    #[DataProvider('providePackageNames')]
    public function testFetchSinglePackage(string $packageName): void
    {
        $entityManager = $this->getEntityManager();
        $package = new Package()
            ->setName($packageName)
            ->setMonth((int)new \DateTime()->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages/' . $packageName);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePopularity($client->getResponse()->getContent());
    }

    public function testQueryRequest(): void
    {
        $entityManager = $this->getEntityManager();
        $pacman = new Package()
            ->setName('pacman')
            ->setMonth(201901);
        $php = new Package()
            ->setName('php')
            ->setMonth(201901);
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['query' => 'pac', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPackagePopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['packagePopularities']);
        $this->assertEquals('pacman', $popularityList['packagePopularities'][0]['name']);
    }

    public function testFilterByDate(): void
    {
        $entityManager = $this->getEntityManager();
        $pacman = new Package()
            ->setName('pacman')
            ->setMonth(201901);
        $php = new Package()
            ->setName('php')
            ->setMonth(201801);
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['startMonth' => '201801', 'endMonth' => '201812']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPackagePopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['packagePopularities']);
        $this->assertEquals('php', $popularityList['packagePopularities'][0]['name']);
    }

    public function testLimitResults(): void
    {
        $entityManager = $this->getEntityManager();
        $pacman = new Package()
            ->setName('pacman')
            ->setMonth(201901);
        $php = new Package()
            ->setName('php')
            ->setMonth(201901);
        $anotherPhp = new Package()
            ->setName('php')
            ->setMonth(201902);
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->persist($anotherPhp);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPackagePopularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['packagePopularities']);
        $this->assertEquals('php', $popularityList['packagePopularities'][0]['name']);
    }

    #[DataProvider('providePackageNames')]
    public function testPackagesSeries(string $packageName): void
    {
        $entityManager = $this->getEntityManager();
        $package = new Package()
            ->setName($packageName)
            ->setMonth(201901);
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages/' . $packageName . '/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPackagePopularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['packagePopularities']);
        $this->assertEquals($packageName, $popularityList['packagePopularities'][0]['name']);
    }

    /**
     * @return list<string[]>
     */
    public static function providePackageNames(): array
    {
        return [
            ['pacman'],
            ['r'],
            ['foo@bar'],
            [str_repeat('a', 191)]
        ];
    }
}

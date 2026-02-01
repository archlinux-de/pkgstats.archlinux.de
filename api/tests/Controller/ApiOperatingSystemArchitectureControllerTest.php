<?php

namespace App\Tests\Controller;

use App\Controller\ApiOperatingSystemArchitectureController;
use App\Entity\OperatingSystemArchitecture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversClass(ApiOperatingSystemArchitectureController::class)]
class ApiOperatingSystemArchitectureControllerTest extends DatabaseTestCase
{
    #[DataProvider('provideArchitectures')]
    public function testFetchAllOperatingSystemArchitectures(string $arch): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemArchitecture = new OperatingSystemArchitecture($arch)->setMonth((int)date('Ym'));
        $entityManager->persist($operatingSystemArchitecture);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPopularityList($client->getResponse()->getContent());
    }

    private function assertAllowsCrossOriginAccess(Response $response): void
    {
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array{'total': int, 'count': int, 'operatingSystemArchitecturePopularities': list<array{'name': string}>}
     */
    private function assertPopularityList(string $json): array
    {
        $this->assertJson($json);

        /** @var array{'total': int, 'count': int, 'operatingSystemArchitecturePopularities': list<array{'name': string}>} $list */
        $list = json_decode($json, true);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('total', $list);
        $this->assertIsInt($list['total']);
        $this->assertArrayHasKey('count', $list);
        $this->assertIsInt($list['count']);
        $this->assertArrayHasKey('operatingSystemArchitecturePopularities', $list);
        $this->assertIsArray($list['operatingSystemArchitecturePopularities']);

        foreach ($list['operatingSystemArchitecturePopularities'] as $item) {
            $this->assertPopularity((string)json_encode($item));
        }

        return $list;
    }

    private function assertPopularity(string $json): void
    {
        $this->assertJson($json);

        $item = json_decode($json, true);
        $this->assertIsArray($item);
        $this->assertArrayHasKey('name', $item);
        $this->assertIsString($item['name']);
        $this->assertArrayHasKey('samples', $item);
        $this->assertIsInt($item['samples']);
        $this->assertArrayHasKey('count', $item);
        $this->assertIsInt($item['count']);
        $this->assertArrayHasKey('popularity', $item);
        $this->assertIsNumeric($item['popularity']);
    }

    public function testFetchEmptyList(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPopularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyArchitecture(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures/x86_64');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPopularity($client->getResponse()->getContent());
    }

    #[DataProvider('provideArchitectures')]
    public function testFetchSingleArchitecture(string $arch): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemArchitecture = new OperatingSystemArchitecture($arch)->setMonth((int)date('Ym'));
        $entityManager->persist($operatingSystemArchitecture);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures/' . $arch);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPopularity($client->getResponse()->getContent());
    }

    public function testQueryRequest(): void
    {
        $entityManager = $this->getEntityManager();
        $x86 = new OperatingSystemArchitecture('x86_64')->setMonth(201901);
        $arm = new OperatingSystemArchitecture('aarch64')->setMonth(201901);
        $entityManager->persist($x86);
        $entityManager->persist($arm);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures', ['query' => 'x86', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['operatingSystemArchitecturePopularities']);
        $this->assertEquals($x86->getName(), $popularityList['operatingSystemArchitecturePopularities'][0]['name']);
    }

    public function testFilterByDate(): void
    {
        $entityManager = $this->getEntityManager();
        $x86 = new OperatingSystemArchitecture('x86_64')->setMonth(201901);
        $arm = new OperatingSystemArchitecture('aarch64')->setMonth(201801);
        $entityManager->persist($x86);
        $entityManager->persist($arm);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request(
            'GET',
            '/api/operating-system-architectures',
            ['startMonth' => '201801', 'endMonth' => '201812']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['operatingSystemArchitecturePopularities']);
        $this->assertEquals($arm->getName(), $popularityList['operatingSystemArchitecturePopularities'][0]['name']);
    }

    public function testLimitResults(): void
    {
        $entityManager = $this->getEntityManager();
        $x86 = new OperatingSystemArchitecture('x86_64')->setMonth(201901);
        $arm = new OperatingSystemArchitecture('aarch64')->setMonth(201901);
        $arm2 = new OperatingSystemArchitecture('aarch64')->setMonth(201902);
        $entityManager->persist($x86);
        $entityManager->persist($arm);
        $entityManager->persist($arm2);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPopularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['operatingSystemArchitecturePopularities']);
        $this->assertEquals($arm->getName(), $popularityList['operatingSystemArchitecturePopularities'][0]['name']);
    }

    #[DataProvider('provideArchitectures')]
    public function testArchitectureSeries(string $arch): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemArchitecture = new OperatingSystemArchitecture($arch)->setMonth(201901);
        $entityManager->persist($operatingSystemArchitecture);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-system-architectures/' . $arch . '/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertPopularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['operatingSystemArchitecturePopularities']);
        $this->assertEquals($arch, $popularityList['operatingSystemArchitecturePopularities'][0]['name']);
    }

    /**
     * @return list<string[]>
     */
    public static function provideArchitectures(): array
    {
        return [
            ['x86_64'],
            ['aarch64'],
            ['i686']
        ];
    }
}

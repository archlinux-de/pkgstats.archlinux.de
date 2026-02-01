<?php

namespace App\Tests\Controller;

use App\Controller\ApiOperatingSystemIdController;
use App\Entity\OperatingSystemId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversClass(ApiOperatingSystemIdController::class)]
class ApiOperatingSystemIdControllerTest extends DatabaseTestCase
{
    #[DataProvider('provideOperatingSystemIds')]
    public function testFetchAllOperatingSystemIds(string $osId): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemId = new OperatingSystemId($osId)->setMonth((int)date('Ym'));
        $entityManager->persist($operatingSystemId);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
    }

    private function assertAllowsCrossOriginAccess(Response $response): void
    {
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array{'total': int, 'count': int, 'operatingSystemIdPopularities': list<array{'id': string}>}
     */
    private function assertOperatingSystemIdPopularityList(string $json): array
    {
        $this->assertJson($json);

        /** @var array{'total': int, 'count': int, 'operatingSystemIdPopularities': list<array{'id': string}>} $operatingSystemIdList */
        $operatingSystemIdList = json_decode($json, true);
        $this->assertIsArray($operatingSystemIdList);
        $this->assertArrayHasKey('total', $operatingSystemIdList);
        $this->assertIsInt($operatingSystemIdList['total']);
        $this->assertArrayHasKey('count', $operatingSystemIdList);
        $this->assertIsInt($operatingSystemIdList['count']);
        $this->assertArrayHasKey('operatingSystemIdPopularities', $operatingSystemIdList);
        $this->assertIsArray($operatingSystemIdList['operatingSystemIdPopularities']);

        foreach ($operatingSystemIdList['operatingSystemIdPopularities'] as $operatingSystemId) {
            $this->assertOperatingSystemIdPopularity((string)json_encode($operatingSystemId));
        }

        return $operatingSystemIdList;
    }

    private function assertOperatingSystemIdPopularity(string $json): void
    {
        $this->assertJson($json);

        $operatingSystemId = json_decode($json, true);
        $this->assertIsArray($operatingSystemId);
        $this->assertArrayHasKey('id', $operatingSystemId);
        $this->assertIsString($operatingSystemId['id']);
        $this->assertArrayHasKey('samples', $operatingSystemId);
        $this->assertIsInt($operatingSystemId['samples']);
        $this->assertArrayHasKey('count', $operatingSystemId);
        $this->assertIsInt($operatingSystemId['count']);
        $this->assertArrayHasKey('popularity', $operatingSystemId);
        $this->assertIsNumeric($operatingSystemId['popularity']);
    }

    public function testFetchEmptyList(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyOperatingSystemId(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems/foo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertOperatingSystemIdPopularity($client->getResponse()->getContent());
    }

    #[DataProvider('provideOperatingSystemIds')]
    public function testFetchSingleOperatingSystemId(string $osId): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemId = new OperatingSystemId($osId)->setMonth((int)date('Ym'));
        $entityManager->persist($operatingSystemId);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems/' . $osId);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertOperatingSystemIdPopularity($client->getResponse()->getContent());
    }

    public function testQueryRequest(): void
    {
        $entityManager = $this->getEntityManager();
        $arch = new OperatingSystemId('arch')->setMonth(201901);
        $endeavouros = new OperatingSystemId('endeavouros')->setMonth(201901);
        $entityManager->persist($arch);
        $entityManager->persist($endeavouros);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems', ['query' => 'endeavour', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['operatingSystemIdPopularities']);
        $this->assertEquals($endeavouros->getId(), $popularityList['operatingSystemIdPopularities'][0]['id']);
    }

    public function testFilterByDate(): void
    {
        $entityManager = $this->getEntityManager();
        $arch = new OperatingSystemId('arch')->setMonth(201901);
        $endeavouros = new OperatingSystemId('endeavouros')->setMonth(201801);
        $entityManager->persist($arch);
        $entityManager->persist($endeavouros);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems', ['startMonth' => '201801', 'endMonth' => '201812']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['operatingSystemIdPopularities']);
        $this->assertEquals($endeavouros->getId(), $popularityList['operatingSystemIdPopularities'][0]['id']);
    }

    public function testLimitResults(): void
    {
        $entityManager = $this->getEntityManager();
        $arch = new OperatingSystemId('arch')->setMonth(201901);
        $endeavouros = new OperatingSystemId('endeavouros')->setMonth(201901);
        $anotherEndeavouros = new OperatingSystemId('endeavouros')->setMonth(201902);
        $entityManager->persist($arch);
        $entityManager->persist($endeavouros);
        $entityManager->persist($anotherEndeavouros);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['operatingSystemIdPopularities']);
        $this->assertEquals($endeavouros->getId(), $popularityList['operatingSystemIdPopularities'][0]['id']);
    }

    #[DataProvider('provideOperatingSystemIds')]
    public function testOperatingSystemIdSeries(string $osId): void
    {
        $entityManager = $this->getEntityManager();
        $operatingSystemId = new OperatingSystemId($osId)->setMonth(201901);
        $entityManager->persist($operatingSystemId);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/operating-systems/' . $osId . '/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertOperatingSystemIdPopularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['operatingSystemIdPopularities']);
        $this->assertEquals($osId, $popularityList['operatingSystemIdPopularities'][0]['id']);
    }

    /**
     * @return list<string[]>
     */
    public static function provideOperatingSystemIds(): array
    {
        return [
            ['arch'],
            ['endeavouros'],
            ['artix']
        ];
    }
}

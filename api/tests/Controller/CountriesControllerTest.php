<?php

namespace App\Tests\Controller;

use App\Entity\Country;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\ApiCountriesController
 */
class CountriesControllerTest extends DatabaseTestCase
{
    #[DataProvider('provideCountryCodes')]
    public function testFetchAllCountries(string $countryCode): void
    {
        $entityManager = $this->getEntityManager();
        $country = (new Country($countryCode))
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($country);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertCountryPupularityList($client->getResponse()->getContent());
    }

    private function assertAllowsCrossOriginAccess(Response $response): void
    {
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    private function assertCountryPupularityList(string $json): array
    {
        $this->assertJson($json);

        $countryList = json_decode($json, true);
        $this->assertIsArray($countryList);
        $this->assertArrayHasKey('total', $countryList);
        $this->assertIsInt($countryList['total']);
        $this->assertArrayHasKey('count', $countryList);
        $this->assertIsInt($countryList['count']);
        $this->assertArrayHasKey('countryPopularities', $countryList);
        $this->assertIsArray($countryList['countryPopularities']);

        foreach ($countryList['countryPopularities'] as $country) {
            $this->assertCountryPupularity((string)json_encode($country));
        }

        return $countryList;
    }

    private function assertCountryPupularity(string $json): void
    {
        $this->assertJson($json);

        $country = json_decode($json, true);
        $this->assertIsArray($country);
        $this->assertArrayHasKey('code', $country);
        $this->assertIsString($country['code']);
        $this->assertArrayHasKey('samples', $country);
        $this->assertIsInt($country['samples']);
        $this->assertArrayHasKey('count', $country);
        $this->assertIsInt($country['count']);
        $this->assertArrayHasKey('popularity', $country);
        $this->assertIsNumeric($country['popularity']);
    }

    public function testFetchEmptyList(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/countries');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertCountryPupularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyCountry(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/countries/fo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertCountryPupularity($client->getResponse()->getContent());
    }

    #[DataProvider('provideCountryCodes')]
    public function testFetchSingleCountry(string $countryCode): void
    {
        $entityManager = $this->getEntityManager();
        $country = (new Country($countryCode))
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($country);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries/' . $countryCode);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertCountryPupularity($client->getResponse()->getContent());
    }

    public function testQueryRequest(): void
    {
        $entityManager = $this->getEntityManager();
        $de = (new Country('DE'))
            ->setMonth(201901);
        $fr = (new Country('FR'))
            ->setMonth(201901);
        $entityManager->persist($de);
        $entityManager->persist($fr);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries', ['query' => 'de', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertCountryPupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['countryPopularities']);
        $this->assertEquals($de->getCode(), $pupularityList['countryPopularities'][0]['code']);
    }

    public function testFilterByDate(): void
    {
        $entityManager = $this->getEntityManager();
        $de = (new Country('DE'))
            ->setMonth(201901);
        $fr = (new Country('FR'))
            ->setMonth(201801);
        $entityManager->persist($de);
        $entityManager->persist($fr);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries', ['startMonth' => '201801', 'endMonth' => '201812']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertCountryPupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['countryPopularities']);
        $this->assertEquals($fr->getCode(), $pupularityList['countryPopularities'][0]['code']);
    }

    public function testLimitResults(): void
    {
        $entityManager = $this->getEntityManager();
        $de = (new Country('DE'))
            ->setMonth(201901);
        $fr = (new Country('FR'))
            ->setMonth(201901);
        $fr2 = (new Country('FR'))
            ->setMonth(201902);
        $entityManager->persist($de);
        $entityManager->persist($fr);
        $entityManager->persist($fr2);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertCountryPupularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $pupularityList['total']);
        $this->assertEquals(1, $pupularityList['count']);
        $this->assertCount(1, $pupularityList['countryPopularities']);
        $this->assertEquals($fr->getCode(), $pupularityList['countryPopularities'][0]['code']);
    }

    #[DataProvider('provideCountryCodes')]
    public function testCountriesSeries(string $countryCode): void
    {
        $entityManager = $this->getEntityManager();
        $country = (new Country($countryCode))
            ->setMonth(201901);
        $entityManager->persist($country);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/countries/' . $countryCode . '/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertCountryPupularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $pupularityList['total']);
        $this->assertEquals(1, $pupularityList['count']);
        $this->assertCount(1, $pupularityList['countryPopularities']);
        $this->assertEquals($countryCode, $pupularityList['countryPopularities'][0]['code']);
    }

    public static function provideCountryCodes(): array
    {
        return [
            ['DE'],
            ['FR'],
            ['US']
        ];
    }
}

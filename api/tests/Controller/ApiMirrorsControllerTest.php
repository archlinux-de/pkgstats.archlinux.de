<?php

namespace App\Tests\Controller;

use App\Controller\ApiMirrorsController;
use App\Entity\Mirror;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversClass(ApiMirrorsController::class)]
class ApiMirrorsControllerTest extends DatabaseTestCase
{
    #[DataProvider('provideMirrorUrls')]
    public function testFetchAllMirrors(string $mirrorUrl): void
    {
        $entityManager = $this->getEntityManager();
        $mirror = new Mirror($mirrorUrl)
            ->setMonth((int)new \DateTime()->format('Ym'));
        $entityManager->persist($mirror);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMirrorPopularityList($client->getResponse()->getContent());
    }

    private function assertAllowsCrossOriginAccess(Response $response): void
    {
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array{'total': int, 'count': int, 'mirrorPopularities': list<array{'url': string}>}
     */
    private function assertMirrorPopularityList(string $json): array
    {
        $this->assertJson($json);

        /** @var array{'total': int, 'count': int, 'mirrorPopularities': list<array{'url': string}>} $mirrorList */
        $mirrorList = json_decode($json, true);
        $this->assertIsArray($mirrorList);
        $this->assertArrayHasKey('total', $mirrorList);
        $this->assertIsInt($mirrorList['total']);
        $this->assertArrayHasKey('count', $mirrorList);
        $this->assertIsInt($mirrorList['count']);
        $this->assertArrayHasKey('mirrorPopularities', $mirrorList);
        $this->assertIsArray($mirrorList['mirrorPopularities']);

        foreach ($mirrorList['mirrorPopularities'] as $mirror) {
            $this->assertMirrorPopularity((string)json_encode($mirror));
        }

        return $mirrorList;
    }

    private function assertMirrorPopularity(string $json): void
    {
        $this->assertJson($json);

        $mirror = json_decode($json, true);
        $this->assertIsArray($mirror);
        $this->assertArrayHasKey('url', $mirror);
        $this->assertIsString($mirror['url']);
        $this->assertArrayHasKey('samples', $mirror);
        $this->assertIsInt($mirror['samples']);
        $this->assertArrayHasKey('count', $mirror);
        $this->assertIsInt($mirror['count']);
        $this->assertArrayHasKey('popularity', $mirror);
        $this->assertIsNumeric($mirror['popularity']);
    }

    public function testFetchEmptyList(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/mirrors');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMirrorPopularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyMirror(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/mirrors/foo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMirrorPopularity($client->getResponse()->getContent());
    }

    #[DataProvider('provideMirrorUrls')]
    public function testFetchSingleMirror(string $mirrorUrl): void
    {
        $entityManager = $this->getEntityManager();
        $mirror = new Mirror($mirrorUrl)
            ->setMonth((int)new \DateTime()->format('Ym'));
        $entityManager->persist($mirror);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors/' . $mirrorUrl);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMirrorPopularity($client->getResponse()->getContent());
    }

    public function testQueryRequest(): void
    {
        $entityManager = $this->getEntityManager();
        $leaseweb = new Mirror('https://mirror.leaseweb.net/archlinux/')
            ->setMonth(201901);
        $localhost = new Mirror('http://localhost:8080/')
            ->setMonth(201901);
        $entityManager->persist($leaseweb);
        $entityManager->persist($localhost);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors', ['query' => 'lease', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertMirrorPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['mirrorPopularities']);
        $this->assertEquals($leaseweb->getUrl(), $popularityList['mirrorPopularities'][0]['url']);
    }

    public function testFilterByDate(): void
    {
        $entityManager = $this->getEntityManager();
        $leaseweb = new Mirror('https://mirror.leaseweb.net/archlinux/')
            ->setMonth(201901);
        $localhost = new Mirror('http://localhost:8080/')
            ->setMonth(201801);
        $entityManager->persist($leaseweb);
        $entityManager->persist($localhost);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors', ['startMonth' => '201801', 'endMonth' => '201812']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertMirrorPopularityList($client->getResponse()->getContent());
        $this->assertCount(1, $popularityList['mirrorPopularities']);
        $this->assertEquals($localhost->getUrl(), $popularityList['mirrorPopularities'][0]['url']);
    }

    public function testLimitResults(): void
    {
        $entityManager = $this->getEntityManager();
        $leaseweb = new Mirror('https://mirror.leaseweb.net/archlinux/')
            ->setMonth(201901);
        $localhost = new Mirror('http://localhost:8080/')
            ->setMonth(201901);
        $anotherLocalhost = new Mirror('http://localhost:8080/')
            ->setMonth(201902);
        $entityManager->persist($leaseweb);
        $entityManager->persist($localhost);
        $entityManager->persist($anotherLocalhost);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertMirrorPopularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['mirrorPopularities']);
        $this->assertEquals($localhost->getUrl(), $popularityList['mirrorPopularities'][0]['url']);
    }

    #[DataProvider('provideMirrorUrls')]
    public function testMirrorsSeries(string $mirrorUrl): void
    {
        $entityManager = $this->getEntityManager();
        $mirror = new Mirror($mirrorUrl)
            ->setMonth(201901);
        $entityManager->persist($mirror);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/mirrors/' . $mirrorUrl . '/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertAllowsCrossOriginAccess($client->getResponse());
        $this->assertIsString($client->getResponse()->getContent());
        $popularityList = $this->assertMirrorPopularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $popularityList['total']);
        $this->assertEquals(1, $popularityList['count']);
        $this->assertCount(1, $popularityList['mirrorPopularities']);
        $this->assertEquals($mirrorUrl, $popularityList['mirrorPopularities'][0]['url']);
    }

    /**
     * @return list<string[]>
     */
    public static function provideMirrorUrls(): array
    {
        return [
            ['https://mirror.leaseweb.net/archlinux/'],
            ['http://localhost:8080/'],
            ['ftp://127.0.0.4:9856/']
        ];
    }
}

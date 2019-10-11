<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Entity\User;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\ApiPackagesController
 */
class ApiPackagesControllerTest extends DatabaseTestCase
{
    public function testFetchAllPackages()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('pacman')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $user = (new User())
            ->setPackages(1)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($package);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePupularityList($client->getResponse()->getContent());
    }

    /**
     * @param string $json
     * @return array
     */
    private function assertPackagePupularityList(string $json): array
    {
        $this->assertJson($json);

        $packageList = json_decode($json, true);
        $this->assertArrayHasKey('total', $packageList);
        $this->assertIsInt($packageList['total']);
        $this->assertArrayHasKey('count', $packageList);
        $this->assertIsInt($packageList['count']);
        $this->assertArrayHasKey('packagePopularities', $packageList);
        $this->assertIsArray($packageList['packagePopularities']);

        foreach ($packageList['packagePopularities'] as $package) {
            $this->assertPackagePupularity((string)json_encode($package));
        }

        return $packageList;
    }

    /**
     * @param string $json
     * @return array
     */
    private function assertPackagePupularity(string $json): array
    {
        $this->assertJson($json);

        $package = json_decode($json, true);
        $this->assertArrayHasKey('name', $package);
        $this->assertIsString($package['name']);
        $this->assertArrayHasKey('samples', $package);
        $this->assertIsInt($package['samples']);
        $this->assertArrayHasKey('count', $package);
        $this->assertIsInt($package['count']);
        $this->assertArrayHasKey('popularity', $package);
        $this->assertIsNumeric($package['popularity']);

        return $package;
    }

    public function testFetchEmptyList()
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePupularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyPackage()
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages/foo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePupularity($client->getResponse()->getContent());
    }

    public function testFetchSinglePackage()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('pacman')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $user = (new User())
            ->setPackages(1)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($package);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages/pacman');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertPackagePupularity($client->getResponse()->getContent());
    }

    public function testQueryRequest()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setName('pacman')
            ->setMonth(201901);
        $php = (new Package())
            ->setName('php')
            ->setMonth(201901);
        $user = (new User())
            ->setPackages(2)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['query' => 'pac', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['packagePopularities']);
        $this->assertEquals('pacman', $pupularityList['packagePopularities'][0]['name']);
    }

    public function testFilterByDate()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setName('pacman')
            ->setMonth(201901);
        $php = (new Package())
            ->setName('php')
            ->setMonth(201801);
        $user = (new User())
            ->setPackages(2)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['startMonth' => '201801', 'endMonth' => '201812']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['packagePopularities']);
        $this->assertEquals('php', $pupularityList['packagePopularities'][0]['name']);
    }

    public function testLimitResults()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setName('pacman')
            ->setMonth(201901);
        $php = (new Package())
            ->setName('php')
            ->setMonth(201901);
        $anotherPhp = (new Package())
            ->setName('php')
            ->setMonth(201902);
        $user = (new User())
            ->setPackages(2)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($pacman);
        $entityManager->persist($php);
        $entityManager->persist($anotherPhp);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages', ['limit' => '1', 'startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $pupularityList['total']);
        $this->assertEquals(1, $pupularityList['count']);
        $this->assertCount(1, $pupularityList['packagePopularities']);
        $this->assertEquals('php', $pupularityList['packagePopularities'][0]['name']);
    }

    public function testPackagesSeries()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('pacman')
            ->setMonth(201901);
        $user = (new User())
            ->setPackages(1)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime())->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($package);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/api/packages/pacman/series', ['startMonth' => 0]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertEquals(1, $pupularityList['total']);
        $this->assertEquals(1, $pupularityList['count']);
        $this->assertCount(1, $pupularityList['packagePopularities']);
        $this->assertEquals('pacman', $pupularityList['packagePopularities'][0]['name']);
    }
}

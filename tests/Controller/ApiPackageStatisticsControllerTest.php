<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Entity\User;
use App\Tests\Util\DatabaseTestCase;

/**
 * @covers \App\Controller\ApiPackageStatisticsController
 */
class ApiPackageStatisticsControllerTest extends DatabaseTestCase
{
    public function testFetchAllPackages()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setPkgname('pacman')
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
        $this->assertArrayHasKey('packages', $packageList);
        $this->assertIsArray($packageList['packages']);

        foreach ($packageList['packages'] as $package) {
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
        $this->assertPackagePupularityList($client->getResponse()->getContent());
    }

    public function testFetchEmptyPackage()
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages/foo');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertPackagePupularity($client->getResponse()->getContent());
    }

    public function testFetchSinglePackage()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setPkgname('pacman')
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
        $this->assertPackagePupularity($client->getResponse()->getContent());
    }

    public function testQueryRequest()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setPkgname('pacman')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $php = (new Package())
            ->setPkgname('php')
            ->setMonth((int)(new \DateTime())->format('Ym'));
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

        $client->request('GET', '/api/packages', ['query' => 'pac']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['packages']);
        $this->assertEquals('pacman', $pupularityList['packages'][0]['name']);
    }

    public function testFilterByDate()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setPkgname('pacman')
            ->setMonth(201901);
        $php = (new Package())
            ->setPkgname('php')
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
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertCount(1, $pupularityList['packages']);
        $this->assertEquals('php', $pupularityList['packages'][0]['name']);
    }

    public function testLimitResults()
    {
        $entityManager = $this->getEntityManager();
        $pacman = (new Package())
            ->setPkgname('pacman')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $php = (new Package())
            ->setPkgname('php')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $anotherPhp = (new Package())
            ->setPkgname('php')
            ->setMonth((int)(new \DateTime('-1 month'))->format('Ym'));
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

        $client->request('GET', '/api/packages', ['limit' => '1']);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $pupularityList = $this->assertPackagePupularityList($client->getResponse()->getContent());
        $this->assertEquals(2, $pupularityList['total']);
        $this->assertEquals(1, $pupularityList['count']);
        $this->assertCount(1, $pupularityList['packages']);
        $this->assertEquals('php', $pupularityList['packages'][0]['name']);
    }
}

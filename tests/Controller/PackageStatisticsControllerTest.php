<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @covers \App\Controller\PackageStatisticsController
 */
class PackageStatisticsControllerTest extends DatabaseTestCase
{
    public function testDatatablesAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/package/datatables', [
            'draw' => 1,
            'length' => 2,
            'columns' => [
                [
                    'data' => 'name',
                    'name' => 'name',
                    'orderable' => false,
                    'searchable' => true,
                    'search' => [
                        'regex' => false,
                        'value' => ''
                    ]
                ]
            ],
            'search' => [
                'regex' => false,
                'value' => 'foo'
            ]
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testPackageAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/package');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString(
            '/package/datatables',
            (string)$crawler->filter('#pkgstats')->attr('data-ajax')
        );
    }

    public function testPackageJsonAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/package.json');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertJson($client->getResponse()->getContent());
        $jsondData = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $jsondData);
        $this->assertEquals([['pkgname' => 'foo', 'count' => 1]], $jsondData);
    }

    public function testPackagesDetailAction()
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
        $entityManager->flush();

        $client = $this->getClient();

        $crawler = $client->request('GET', '/packages/foo');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertStringContainsString(
            'foo',
            (string)$crawler->filter('h1')->text()
        );
    }

    public function testPackagesDetailActionReturns404OnUnknownPackage()
    {
        $client = $this->getClient();

        $client->request('GET', '/packages/foo');
        $this->assertTrue($client->getResponse()->isNotFound());
    }
}

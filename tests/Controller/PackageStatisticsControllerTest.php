<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Tests\Util\DatabaseTestCase;

/**
 * @covers \App\Controller\PackageStatisticsController
 */
class PackageStatisticsControllerTest extends DatabaseTestCase
{
    public function testDatatablesAction()
    {
        $entityManager = $this->getEntityManager();
        $pacakge = (new Package())
            ->setPkgname('foo')
            ->setMonth(201812);
        $entityManager->persist($pacakge);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/package/datatables', [
            'draw' => 1,
            'length' => 2,
            'columns' => [
                [
                    'data' => 'pkgname',
                    'name' => 'pkgname',
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

        $this->assertTrue($client->getResponse()->isSuccessful(), $client->getResponse()->getContent());
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testPackageAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/package');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains('/package/datatables', $crawler->filter('#pkgstats')->attr('data-ajax'));
    }

    public function testPackageJsonAction()
    {
        $entityManager = $this->getEntityManager();
        $pacakge = (new Package())
            ->setPkgname('foo')
            ->setMonth(201812);
        $entityManager->persist($pacakge);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/package.json');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertJson($client->getResponse()->getContent());
    }
}

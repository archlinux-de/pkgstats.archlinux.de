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
        $package = (new Package())
            ->setPkgname('foo')
            ->setMonth((int)(new \DateTime())->format('Ym'));
        $entityManager->persist($package);
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
        $this->assertStringContainsString(
            '/package/datatables',
            (string)$crawler->filter('#pkgstats')->attr('data-ajax')
        );
    }
}

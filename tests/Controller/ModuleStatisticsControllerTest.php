<?php

namespace App\Tests\Controller;

use App\Entity\Module;
use App\Tests\Util\DatabaseTestCase;

/**
 * @covers \App\Controller\ModuleStatisticsController
 */
class ModuleStatisticsControllerTest extends DatabaseTestCase
{
    public function testDatatablesAction()
    {
        $entityManager = $this->getEntityManager();
        $pacakge = (new Module())
            ->setName('foo')
            ->setMonth(201812);
        $entityManager->persist($pacakge);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/module/datatables', [
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

        $this->assertTrue($client->getResponse()->isSuccessful(), $client->getResponse()->getContent());
        $this->assertJson($client->getResponse()->getContent());
    }

    public function testModuleAction()
    {
        $client = $this->getClient();

        $crawler = $client->request('GET', '/module');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains('/module/datatables', $crawler->filter('#modules')->attr('data-ajax'));
    }

    public function testModuleJsonAction()
    {
        $entityManager = $this->getEntityManager();
        $pacakge = (new Module())
            ->setName('foo')
            ->setMonth(201812);
        $entityManager->persist($pacakge);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', '/module.json');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertJson($client->getResponse()->getContent());
    }
}

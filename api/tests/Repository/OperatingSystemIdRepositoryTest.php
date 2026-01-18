<?php

namespace App\Tests\Repository;

use App\Entity\OperatingSystemId;
use App\Repository\OperatingSystemIdRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class OperatingSystemIdRepositoryTest extends DatabaseTestCase
{
    public function testFindAll(): void
    {
        $operatingSystemId = new OperatingSystemId('arch')->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($operatingSystemId);
        $entityManager->flush();
        $entityManager->clear();

        /** @var OperatingSystemIdRepository $operatingSystemIdRepository */
        $operatingSystemIdRepository = $this->getRepository(OperatingSystemId::class);
        $operatingSystemIds = $operatingSystemIdRepository->findAll();
        $this->assertCount(1, $operatingSystemIds);
        $this->assertEquals([$operatingSystemId], $operatingSystemIds);
    }
}

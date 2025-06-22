<?php

namespace App\Tests\Repository;

use App\Entity\OperatingSystemArchitecture;
use App\Repository\OperatingSystemArchitectureRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class OperatingSystemArchitectureRepositoryTest extends DatabaseTestCase
{
    public function testFindAll(): void
    {
        $operatingSystemArchitecture = new OperatingSystemArchitecture('a')->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($operatingSystemArchitecture);
        $entityManager->flush();
        $entityManager->clear();

        /** @var OperatingSystemArchitectureRepository $operatingSystemArchitectureRepository */
        $operatingSystemArchitectureRepository = $this->getRepository(OperatingSystemArchitecture::class);
        $operatingSystemArchitectures = $operatingSystemArchitectureRepository->findAll();
        $this->assertCount(1, $operatingSystemArchitectures);
        $this->assertEquals([$operatingSystemArchitecture], $operatingSystemArchitectures);
    }
}

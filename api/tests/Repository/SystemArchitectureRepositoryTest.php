<?php

namespace App\Tests\Repository;

use App\Entity\SystemArchitecture;
use App\Repository\SystemArchitectureRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class SystemArchitectureRepositoryTest extends DatabaseTestCase
{
    public function testFindAll(): void
    {
        $systemArchitecture = new SystemArchitecture('a')->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($systemArchitecture);
        $entityManager->flush();
        $entityManager->clear();

        /** @var SystemArchitectureRepository $systemArchitectureRepository */
        $systemArchitectureRepository = $this->getRepository(SystemArchitecture::class);
        $systemArchitectures = $systemArchitectureRepository->findAll();
        $this->assertCount(1, $systemArchitectures);
        $this->assertEquals([$systemArchitecture], $systemArchitectures);
    }
}

<?php

namespace App\Tests\Repository;

use App\Entity\Mirror;
use App\Repository\MirrorRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class MirrorRepositoryTest extends DatabaseTestCase
{
    public function testFindAll(): void
    {
        $mirror = (new Mirror('a'))->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($mirror);
        $entityManager->flush();
        $entityManager->clear();

        /** @var MirrorRepository $mirrorRepository */
        $mirrorRepository = $this->getRepository(Mirror::class);
        $mirrors = $mirrorRepository->findAll();
        $this->assertCount(1, $mirrors);
        $this->assertEquals([$mirror], $mirrors);
    }
}

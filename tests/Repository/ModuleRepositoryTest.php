<?php

namespace App\Tests\Repository;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use App\Tests\Util\DatabaseTestCase;

class ModuleRepositoryTest extends DatabaseTestCase
{
    public function testInitialCount()
    {
        $module = (new Module())->setName('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($module);
        $entityManager->flush();
        $entityManager->clear();

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = $this->getRepository(Module::class);
        /** @var Module $persistedModule */
        $persistedModule = $moduleRepository->findOneBy(['name' => 'a', 'month' => 201812]);

        $this->assertEquals(1, $persistedModule->getCount());
    }

    public function testCountIncreases()
    {
        $moduleA = (new Module())->setName('a')->setMonth(201812);
        $moduleB = (new Module())->setName('a')->setMonth(201812);
        $entityManager = $this->getEntityManager();
        $entityManager->merge($moduleA);
        $entityManager->flush();
        $entityManager->merge($moduleB);
        $entityManager->flush();
        $entityManager->clear();

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = $this->getRepository(Module::class);
        /** @var Module $persistedModule */
        $persistedModule = $moduleRepository->findOneBy(['name' => 'a', 'month' => 201812]);

        $this->assertEquals(2, $persistedModule->getCount());
    }
}

<?php

namespace App\Tests\Repository;

use App\Entity\Country;
use App\Repository\CountryRepository;
use SymfonyDatabaseTest\DatabaseTestCase;

class CountryRepositoryTest extends DatabaseTestCase
{
    public function testFindAll(): void
    {
        $country = (new Country('a'))->setMonth(201810)->incrementCount();
        $entityManager = $this->getEntityManager();
        $entityManager->persist($country);
        $entityManager->flush();
        $entityManager->clear();

        /** @var CountryRepository $countryRepository */
        $countryRepository = $this->getRepository(Country::class);
        $countries = $countryRepository->findAll();
        $this->assertCount(1, $countries);
        $this->assertEquals([$country], $countries);
    }
}

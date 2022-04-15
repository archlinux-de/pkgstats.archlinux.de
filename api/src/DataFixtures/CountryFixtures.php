<?php

namespace App\DataFixtures;

use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use League\ISO3166\ISO3166;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CountryFixtures extends Fixture
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ISO3166 $iso3166,
        private readonly Months $months
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createCountries($month) as $country) {
                $manager->persist($country);
            }
            $manager->flush();
        }
    }

    /**
     * @return iterable<Country>
     */
    private function createCountries(int $month): iterable
    {
        foreach ($this->iso3166 as $countryCode) {
            assert(is_array($countryCode));
            $country = (new Country($countryCode['alpha2']))->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 6_000); $i++) {
                $country->incrementCount();
            }
            assert($this->validator->validate($country)->count() === 0);
            yield $country;
        }
    }
}

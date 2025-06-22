<?php

namespace App\DataFixtures;

use App\Entity\OperatingSystemArchitecture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OperatingSystemArchitectureFixtures extends Fixture
{
    public function __construct(private readonly ValidatorInterface $validator, private readonly Months $months)
    {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createOperatingSystemArchitectures($month) as $operatingSystemArchitecture) {
                $manager->persist($operatingSystemArchitecture);
            }

            $manager->flush();
        }
    }

    /**
     * @return iterable<OperatingSystemArchitecture>
     */
    private function createOperatingSystemArchitectures(int $month): iterable
    {
        foreach (OperatingSystemArchitecture::ARCHITECTURES as $architectureName) {
            $operatingSystemArchitecture = new OperatingSystemArchitecture($architectureName)->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 20_000); $i++) {
                $operatingSystemArchitecture->incrementCount();
            }
            assert($this->validator->validate($operatingSystemArchitecture)->count() === 0);
            yield $operatingSystemArchitecture;
        }
    }
}

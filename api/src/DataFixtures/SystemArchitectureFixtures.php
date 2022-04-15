<?php

namespace App\DataFixtures;

use App\Entity\SystemArchitecture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SystemArchitectureFixtures extends Fixture
{
    public function __construct(private readonly ValidatorInterface $validator, private readonly Months $months)
    {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createSystemArchitectures($month) as $systemArchitecture) {
                $manager->persist($systemArchitecture);
            }

            $manager->flush();
        }
    }

    /**
     * @return iterable<SystemArchitecture>
     */
    private function createSystemArchitectures(int $month): iterable
    {
        foreach (SystemArchitecture::ARCHITECTURES as $architectureName) {
            $systemArchitecture = (new SystemArchitecture($architectureName))->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 20_000); $i++) {
                $systemArchitecture->incrementCount();
            }
            assert($this->validator->validate($systemArchitecture)->count() === 0);
            yield $systemArchitecture;
        }
    }
}

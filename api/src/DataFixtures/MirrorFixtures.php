<?php

namespace App\DataFixtures;

use App\Entity\Mirror;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MirrorFixtures extends Fixture
{
    public function __construct(private readonly ValidatorInterface $validator, private readonly Months $months)
    {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createMirrors($month) as $mirror) {
                $manager->persist($mirror);
            }
            $manager->flush();
        }
    }

    /**
     * @return iterable<Mirror>
     */
    private function createMirrors(int $month): iterable
    {
        for ($j = 0; $j < 100; $j++) {
            $mirror = new Mirror(sprintf('http://%d.localhost/', $j))->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 2_000); $i++) {
                $mirror->incrementCount();
            }
            assert($this->validator->validate($mirror)->count() === 0);
            yield $mirror;
        }
    }
}

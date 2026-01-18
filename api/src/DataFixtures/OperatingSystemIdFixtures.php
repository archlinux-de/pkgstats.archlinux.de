<?php

namespace App\DataFixtures;

use App\Entity\OperatingSystemId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OperatingSystemIdFixtures extends Fixture
{
    private const array OPERATING_SYSTEM_IDS = [
        'arch',
        'artix',
        'endeavouros',
        'manjaro',
        'garuda',
        'cachyos',
    ];

    public function __construct(private readonly ValidatorInterface $validator, private readonly Months $months)
    {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createOperatingSystemIds($month) as $operatingSystemId) {
                $manager->persist($operatingSystemId);
            }

            $manager->flush();
        }
    }

    /**
     * @return iterable<OperatingSystemId>
     */
    private function createOperatingSystemIds(int $month): iterable
    {
        foreach (self::OPERATING_SYSTEM_IDS as $id) {
            $operatingSystemId = new OperatingSystemId($id)->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 20_000); $i++) {
                $operatingSystemId->incrementCount();
            }
            assert($this->validator->validate($operatingSystemId)->count() === 0);
            yield $operatingSystemId;
        }
    }
}

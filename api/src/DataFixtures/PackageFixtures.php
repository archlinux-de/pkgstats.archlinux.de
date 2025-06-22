<?php

namespace App\DataFixtures;

use App\Entity\Package;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PackageFixtures extends Fixture
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly Months $months,
        private readonly string $environment
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        ini_set('memory_limit', '-1');
        mt_srand(42);

        foreach ($this->months as $month) {
            foreach ($this->createPackages($month) as $package) {
                $manager->persist($package);
            }
            $manager->flush();
        }
    }

    /**
     * @return iterable<Package>
     */
    private function createPackages(int $month): iterable
    {
        $packageNames = $this->environment === 'dev'
            ? array_unique([...$this->getFunPackages(), ...$this->getPackages()])
            : $this->getPackages();

        foreach ($packageNames as $packageName) {
            $package = new Package()->setName($packageName)->setMonth($month);
            for ($i = 0; $i < mt_rand(1, 20_000); $i++) {
                $package->incrementCount();
            }
            assert($this->validator->validate($package)->count() === 0);
            yield $package;
        }
    }

    /**
     * @return iterable<string>
     */
    private function getFunPackages(): iterable
    {
        $funPackages = json_decode((string)file_get_contents(__DIR__ . '/../../../app/src/config/fun.json'), true);
        assert(is_iterable($funPackages));

        foreach ($funPackages as $funCategories) {
            assert(is_iterable($funCategories));
            foreach ($funCategories as $funPackage) {
                assert(is_string($funPackage));
                yield $funPackage;
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function getPackages(): iterable
    {
        // package list is created by `pacman -Qq > api/src/DataFixtures/packages.txt`
        $packages = file(__DIR__ . '/packages.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return is_array($packages) ? $packages : [];
    }
}

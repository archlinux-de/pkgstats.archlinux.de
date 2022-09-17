<?php

namespace App\Entity;

use App\Repository\OperatingSystemArchitectureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass:OperatingSystemArchitectureRepository::class)]
#[ORM\Index(columns: ['month', 'name'], name: 'operating_sytem_architecture_month_name')]
#[ORM\Index(columns: ['month'], name: 'operating_sytem_architecture_month')]
class OperatingSystemArchitecture
{
    public const ARCHITECTURES = ['x86_64', 'i686', 'aarch64', 'armv7h', 'armv6h', 'arm', 'riscv64'];

    #[ORM\Column(length:10)]
    #[ORM\Id]
    #[Assert\Choice(self::ARCHITECTURES)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $count = 1;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): OperatingSystemArchitecture
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): OperatingSystemArchitecture
    {
        $this->count++;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}

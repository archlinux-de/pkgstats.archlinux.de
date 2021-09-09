<?php

namespace App\Entity;

use App\Repository\SystemArchitectureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SystemArchitectureRepository::class)]
#[ORM\Index(columns: ['month', 'name'], name: 'sytem_architecture_month_name')]
#[ORM\Index(columns: ['month'], name: 'sytem_architecture_month')]
class SystemArchitecture
{
    #[ORM\Column(length: 10)]
    #[ORM\Id]
    #[Assert\Choice(['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4', 'i686', 'aarch64', 'armv7', 'armv6', 'armv5'])]
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

    public function setMonth(int $month): SystemArchitecture
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): SystemArchitecture
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

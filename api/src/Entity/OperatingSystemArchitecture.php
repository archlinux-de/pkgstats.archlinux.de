<?php

namespace App\Entity;

use App\Repository\OperatingSystemArchitectureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OperatingSystemArchitectureRepository::class)]
#[ORM\Index(name: 'operating_sytem_architecture_month_name', columns: ['month', 'name'])]
#[ORM\Index(name: 'operating_sytem_architecture_month_count', columns: ['month', 'count'])]
class OperatingSystemArchitecture implements \Stringable
{
    public const array ARCHITECTURES = [
        'x86_64', 'i686', 'i586',
        'aarch64', 'armv7h', 'armv6h', 'armv7l', 'armv6l', 'armv5tel', 'arm',
        'riscv64',
        'loongarch64'
    ];
    private const string NAME_CHARS = '[a-z0-9_]';
    public const string NAME_REGEXP = '^' . self::NAME_CHARS . '{1,15}$';
    public const string NAME_REGEXP_OPTIONAL = '^' . self::NAME_CHARS . '*$';

    #[ORM\Column(length: 15)]
    #[ORM\Id]
    #[Assert\Choice(choices: self::ARCHITECTURES)]
    private string $name;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
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

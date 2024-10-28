<?php

namespace App\Entity;

use App\Repository\PackageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PackageRepository::class)]
#[ORM\Index(columns: ['month', 'name'], name: 'package_month_name')]
#[ORM\Index(columns: ['month', 'count'], name: 'package_month_count')]
class Package
{
    #[ORM\Column(length: 191)]
    #[ORM\Id]
    #[Assert\Length(max: 191)]
    #[Assert\Regex('/' . self::NAME_REGEXP . '/')]
    private string $name;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public const string NAME_REGEXP = '^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]{0,190}$';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Package
    {
        $this->name = $name;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): Package
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): Package
    {
        $this->count++;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }
}

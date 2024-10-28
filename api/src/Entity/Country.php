<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\Index(columns: ['month', 'code'], name: 'country_month_code')]
#[ORM\Index(columns: ['month', 'count'], name: 'country_month_count')]
class Country
{
    public const string CODE_REGEXP = '^[a-zA-Z]{1,2}$';

    #[ORM\Column(length: 2)]
    #[ORM\Id]
    #[Assert\NotBlank]
    // Explicitly list Kosovo as it is currently not recognized by Symfony
    #[Assert\AtLeastOneOf([new Assert\Country(), new Assert\EqualTo("XK")])]
    private string $code;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): Country
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): Country
    {
        $this->count++;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }
}

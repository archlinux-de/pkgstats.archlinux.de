<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\Index(columns: ['month', 'code'], name: 'country_month_code')]
#[ORM\Index(columns: ['month', 'count'], name: 'country_month_count')]
class Country
{
    #[ORM\Column(length: 2)]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\Country]
    private string $code;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public const string CODE_REGEXP = '^[a-zA-Z]{1,2}$';

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

<?php

namespace App\Entity;

use App\Repository\OperatingSystemIdRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OperatingSystemIdRepository::class)]
#[ORM\Index(name: 'operating_sytem_id_month_id', columns: ['month', 'id'])]
#[ORM\Index(name: 'operating_sytem_id_month_count', columns: ['month', 'count'])]
class OperatingSystemId implements \Stringable
{
    private const string ID_CHARS = '[0-9a-z\._-]';
    public const string ID_REGEXP = '^' . self::ID_CHARS . '{1,50}$';
    public const string ID_REGEXP_OPTIONAL = '^' . self::ID_CHARS . '*$';

    #[ORM\Column(length: 50)]
    #[ORM\Id]
    #[Assert\Length(max: 50)]
    #[Assert\Regex('/' . self::ID_CHARS . '+$/')]
    private string $id;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): OperatingSystemId
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): OperatingSystemId
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
        return $this->getId();
    }
}

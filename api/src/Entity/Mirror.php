<?php

namespace App\Entity;

use App\Repository\MirrorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MirrorRepository::class)]
#[ORM\Index(name: 'mirror_month_url', columns: ['month', 'url'])]
#[ORM\Index(name: 'mirror_month_count', columns: ['month', 'count'])]
class Mirror
{
    #[ORM\Column(length: 191)]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https', 'ftp'], requireTld: true)]
    private string $url;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public const string URL_REGEXP = '^[0-9a-zA-Z\-\._~\/\:]{1,191}$';

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): Mirror
    {
        $this->month = $month;
        return $this;
    }

    public function incrementCount(): Mirror
    {
        $this->count++;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }
}

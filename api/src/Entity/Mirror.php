<?php

namespace App\Entity;

use App\Repository\MirrorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MirrorRepository::class)]
#[ORM\Index(columns: ['month', 'url'], name: 'mirror_month_url')]
#[ORM\Index(columns: ['month'], name: 'mirror_month')]
class Mirror
{
    #[ORM\Column(length: 191)]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Url(protocols: ['http', 'https', 'ftp'])]
    private string $url;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\Id]
    #[Assert\NotBlank]
    #[Assert\DateTime('Ym')]
    private int $month;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $count = 1;

    public const URL_REGEXP = '^[0-9a-zA-Z\-\._~\/\:]{1,191}$';

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

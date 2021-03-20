<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="mirror_month_url", columns={"month", "url"}),
 *          @ORM\Index(name="mirror_month", columns={"month"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\MirrorRepository")
 */
class Mirror
{
    /**
     * @var string
     * @Assert\NotBlank
     * @Assert\Length(max=255)
     * @Assert\Url(protocols={"http", "https", "ftp"})
     *
     * @ORM\Column(name="url", type="string", length=191)
     * @ORM\Id
     */
    private $url;

    /**
     * @var integer
     * @Assert\NotBlank
     * @Assert\DateTime("Ym")
     *
     * @ORM\Column(name="month", type="integer")
     * @ORM\Id
     */
    private $month;

    /**
     * @var integer
     * @Assert\Positive
     *
     * @ORM\Column(name="count", type="integer", nullable=false)
     */
    private $count = 1;

    /**
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return Mirror
     */
    public function setUrl(string $url): Mirror
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return int
     */
    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @param int $month
     * @return Mirror
     */
    public function setMonth(int $month): Mirror
    {
        $this->month = $month;
        return $this;
    }

    /**
     * @return Mirror
     */
    public function incrementCount(): Mirror
    {
        $this->count++;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getCount(): ?int
    {
        return $this->count;
    }
}

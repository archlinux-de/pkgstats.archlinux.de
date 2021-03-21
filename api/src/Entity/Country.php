<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="country_month_code", columns={"month", "code"}),
 *          @ORM\Index(name="country_month", columns={"month"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\CountryRepository")
 */
class Country
{
    /**
     * @var string
     * @Assert\NotBlank
     * @Assert\Country
     *
     * @ORM\Column(name="code", type="string", length=2)
     * @ORM\Id
     */
    private $code;

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
     * @param string $code
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
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
     * @return Country
     */
    public function setMonth(int $month): Country
    {
        $this->month = $month;
        return $this;
    }

    /**
     * @return Country
     */
    public function incrementCount(): Country
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

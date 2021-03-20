<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="operating_sytem_architecture_month_name", columns={"month", "name"}),
 *          @ORM\Index(name="operating_sytem_architecture_month", columns={"month"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\OperatingSystemArchitectureRepository")
 */
class OperatingSystemArchitecture
{
    /**
     * @var string
     * @Assert\Choice({"x86_64", "i686", "aarch64", "armv7h", "armv6h", "arm"})
     *
     * @ORM\Column(name="name", type="string", length=10)
     * @ORM\Id
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="month", type="integer")
     * @ORM\Id
     */
    private $month;

    /**
     * @var integer
     *
     * @ORM\Column(name="count", type="integer", nullable=false)
     */
    private $count = 1;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return OperatingSystemArchitecture
     */
    public function setName(string $name): OperatingSystemArchitecture
    {
        $this->name = $name;
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
     * @return OperatingSystemArchitecture
     */
    public function setMonth(int $month): OperatingSystemArchitecture
    {
        $this->month = $month;
        return $this;
    }

    /**
     * @return OperatingSystemArchitecture
     */
    public function incrementCount(): OperatingSystemArchitecture
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

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}

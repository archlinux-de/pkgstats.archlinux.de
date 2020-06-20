<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="month_name", columns={"month", "name"}),
 *          @ORM\Index(name="month", columns={"month"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\PackageRepository")
 */
class Package
{
    /**
     * @var string
     * @Assert\Length(max=191)
     * @Assert\Regex("/^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]*$/")
     *
     * @ORM\Column(name="name", type="string", length=191)
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Package
     */
    public function setName(string $name): Package
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
     * @return Package
     */
    public function setMonth(int $month): Package
    {
        $this->month = $month;
        return $this;
    }

    /**
     * @return Package
     */
    public function incrementCount(): Package
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

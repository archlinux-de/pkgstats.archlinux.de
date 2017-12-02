<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity
 */
class Package
{
    /**
     * @var string
     *
     * @ORM\Column(name="pkgname", type="string", length=255)
     * @ORM\Id
     */
    private $pkgname;

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
    private $count;

    /**
     * @return string
     */
    public function getPkgname(): string
    {
        return $this->pkgname;
    }

    /**
     * @return int
     */
    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="mirror", columns={"mirror"}),
 *          @ORM\Index(name="ip", columns={"ip", "time"}),
 *          @ORM\Index(name="countryCode", columns={"countryCode"})
 *     }
 * )
 * @ORM\Entity
 */
class User
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="ip", type="string", length=40, nullable=false)
     */
    private $ip;

    /**
     * @var integer
     *
     * @ORM\Column(name="time", type="integer", nullable=false)
     */
    private $time;

    /**
     * @var string
     *
     * @ORM\Column(name="arch", type="string", length=10, nullable=false)
     */
    private $arch;

    /**
     * @var string
     *
     * @ORM\Column(name="cpuarch", type="string", length=10, nullable=true)
     */
    private $cpuarch;

    /**
     * @var string
     *
     * @ORM\Column(name="countryCode", type="string", length=2, nullable=true)
     */
    private $countrycode;

    /**
     * @var string
     *
     * @ORM\Column(name="mirror", type="string", length=255, nullable=true)
     */
    private $mirror;

    /**
     * @var integer
     *
     * @ORM\Column(name="packages", type="smallint", nullable=false)
     */
    private $packages;

    /**
     * @var integer
     *
     * @ORM\Column(name="modules", type="smallint", nullable=true)
     */
    private $modules;

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getArch(): string
    {
        return $this->arch;
    }

    /**
     * @return string
     */
    public function getCpuarch(): string
    {
        return $this->cpuarch;
    }

    /**
     * @return string
     */
    public function getCountrycode(): string
    {
        return $this->countrycode;
    }

    /**
     * @return string
     */
    public function getMirror(): string
    {
        return $this->mirror;
    }

    /**
     * @return int
     */
    public function getPackages(): int
    {
        return $this->packages;
    }

    /**
     * @return int
     */
    public function getModules(): int
    {
        return $this->modules;
    }
}

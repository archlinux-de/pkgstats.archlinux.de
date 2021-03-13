<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(name="mirror", columns={"mirror"}),
 *          @ORM\Index(name="ip", columns={"ip", "time"}),
 *          @ORM\Index(name="countryCode", columns={"countryCode"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
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
     * @Assert\Choice(callback="getValidArches")
     *
     * @ORM\Column(name="arch", type="string", length=10, nullable=false)
     */
    private $arch;

    /**
     * @var string|null
     * @Assert\Choice(callback="getValidCpuArches")
     *
     * @ORM\Column(name="cpuarch", type="string", length=10, nullable=true)
     */
    private $cpuarch;

    /**
     * @var string|null
     *
     * @ORM\Column(name="countryCode", type="string", length=2, nullable=true)
     */
    private $countrycode;

    /**
     * @var string|null
     * @Assert\Length(max=255)
     * @Assert\Url(protocols={"http", "https", "ftp"})
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
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     * @return User
     */
    public function setIp(string $ip): User
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @param int $time
     * @return User
     */
    public function setTime(int $time): User
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return string
     */
    public function getArch(): string
    {
        return $this->arch;
    }

    /**
     * @param string $arch
     * @return User
     */
    public function setArch(string $arch): User
    {
        $this->arch = $arch;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCpuarch(): ?string
    {
        return $this->cpuarch;
    }

    /**
     * @param string|null $cpuarch
     * @return User
     */
    public function setCpuarch(?string $cpuarch): User
    {
        $this->cpuarch = $cpuarch;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCountrycode(): ?string
    {
        return $this->countrycode;
    }

    /**
     * @param string|null $countrycode
     * @return User
     */
    public function setCountrycode(?string $countrycode): User
    {
        $this->countrycode = $countrycode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMirror(): ?string
    {
        return $this->mirror;
    }

    /**
     * @param string|null $mirror
     * @return User
     */
    public function setMirror(?string $mirror): User
    {
        $this->mirror = $mirror;
        return $this;
    }

    /**
     * @return int
     */
    public function getPackages(): int
    {
        return $this->packages;
    }

    /**
     * @param int $packages
     * @return User
     */
    public function setPackages(int $packages): User
    {
        $this->packages = $packages;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getValidArches(): array
    {
        switch ($this->getCpuarch()) {
            case 'x86_64':
            case 'x86_64_v2':
            case 'x86_64_v3':
            case 'x86_64_v4':
                $validArches = ['x86_64', 'i686'];
                break;
            case 'i686':
                $validArches = ['i686'];
                break;
            case 'aarch64':
                $validArches = ['aarch64', 'armv7h', 'armv6h', 'arm'];
                break;
            case 'armv7':
                $validArches = ['armv7h', 'armv6h', 'arm'];
                break;
            case 'armv6':
                $validArches = ['armv6h', 'arm'];
                break;
            case 'armv5':
                $validArches = ['arm'];
                break;
            default:
                $validArches = [];
        }
        return $validArches;
    }

    /**
     * @return string[]
     */
    public function getValidCpuArches(): array
    {
        switch ($this->getArch()) {
            case 'x86_64':
                $validCpuArches = ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'];
                break;
            case 'i686':
                $validCpuArches = ['i686', 'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'];
                break;
            case 'aarch64':
                $validCpuArches = ['aarch64'];
                break;
            case 'armv6h':
                $validCpuArches = ['armv6', 'armv7', 'aarch64'];
                break;
            case 'armv7h':
                $validCpuArches = ['armv7', 'aarch64'];
                break;
            case 'arm':
                $validCpuArches = ['armv5', 'armv6', 'armv7', 'aarch64'];
                break;
            default:
                $validCpuArches = [];
        }

        return $validCpuArches;
    }
}

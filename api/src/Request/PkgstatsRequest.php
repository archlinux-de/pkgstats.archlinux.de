<?php

namespace App\Request;

use App\Entity\Package;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class PkgstatsRequest
{
    /**
     * @var string
     * @Assert\NotBlank()
     * @Assert\Regex(pattern="/^(2\.[345]|3(\.[0-9]+)?)(\.[0-9]+(-[\w-]+)?)?$/")
     */
    private $version;

    /**
     * @var User
     * @Assert\Valid()
     */
    private $user;

    /**
     * @var Package[]
     * @Assert\Valid()
     * @Assert\Count(min=1, max=10000)
     */
    private $packages = [];

    /**
     * @param string $version
     * @param User $user
     */
    public function __construct(string $version, User $user)
    {
        $this->version = $version;
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Package[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * @param Package $package
     * @return PkgstatsRequest
     */
    public function addPackage(Package $package): PkgstatsRequest
    {
        $this->packages[] = $package;
        return $this;
    }
}

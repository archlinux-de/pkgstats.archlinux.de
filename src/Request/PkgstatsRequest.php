<?php

namespace App\Request;

use App\Entity\Module;
use App\Entity\Package;
use App\Entity\User;

class PkgstatsRequest
{
    /** @var string */
    private $version;
    /** @var User */
    private $user;
    /** @var Package[] */
    private $packages = [];
    /** @var Module[] */
    private $modules = [];
    /** @var bool */
    private $quiet = false;

    /**
     * PkgstatsRequest constructor.
     * @param string $version
     * @param User $user
     */
    public function __construct(string $version, User $user)
    {
        $this->version = $version;
        $this->user = $user;
    }

    /**
     * @return bool
     */
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * @param bool $quiet
     * @return PkgstatsRequest
     */
    public function setQuiet(bool $quiet): PkgstatsRequest
    {
        $this->quiet = $quiet;
        return $this;
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
     * @return Module[]
     */
    public function getModules(): array
    {
        return $this->modules;
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

    /**
     * @param Module $module
     * @return PkgstatsRequest
     */
    public function addModule(Module $module): PkgstatsRequest
    {
        $this->modules[] = $module;
        return $this;
    }
}

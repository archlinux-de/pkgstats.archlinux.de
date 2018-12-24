<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\PackageRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Package
{
    /**
     * @var string
     * @Assert\Length(max=255)
     * @Assert\Regex("/^[^-]+\S*$/")
     *
     * @ORM\Column(name="pkgname", type="string", length=191)
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
     * @var integer|null
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
     * @param string $pkgname
     * @return Package
     */
    public function setPkgname(string $pkgname): Package
    {
        $this->pkgname = $pkgname;
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
     * @return int|null
     */
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * @param int $count
     * @return Package
     */
    protected function setCount(int $count): Package
    {
        $this->count = $count;
        return $this;
    }

    /**
     * @ORM\PreUpdate
     * @param PreUpdateEventArgs $args
     */
    public function incrementCountOnUpdate(PreUpdateEventArgs $args): void
    {
        $args->setNewValue('count', $args->getOldValue('count') + 1);
    }

    /**
     * @ORM\PrePersist
     * @param LifecycleEventArgs $args
     */
    public function setCountOnPersist(LifecycleEventArgs $args): void
    {
        $args->getEntity()->setCount(1);
    }
}

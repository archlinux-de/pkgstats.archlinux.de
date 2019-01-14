<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\ModuleRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Module
{
    /**
     * @var string
     * @Assert\Length(max=255)
     * @Assert\Regex("/^[\w\-]+$/")
     *
     * @ORM\Column(name="name", type="string", length=50)
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
     * @var integer|null
     *
     * @ORM\Column(name="count", type="integer", nullable=false)
     */
    private $count;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Module
     */
    public function setName(string $name): Module
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
     * @return Module
     */
    public function setMonth(int $month): Module
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
     * @return Module
     */
    protected function setCount(int $count): Module
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
        /** @var Module $module */
        $module = $args->getEntity();
        $module->setCount(1);
    }
}

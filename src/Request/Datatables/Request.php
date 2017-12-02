<?php

namespace App\Request\Datatables;

use Symfony\Component\Validator\Constraints as Assert;

class Request implements \JsonSerializable
{
    /**
     * @var int
     * @Assert\GreaterThanOrEqual(1)
     */
    private $draw;
    /**
     * @var int
     * @Assert\GreaterThanOrEqual(0)
     */
    private $start;
    /**
     * @var int
     * @Assert\GreaterThanOrEqual(1)
     * @Assert\LessThanOrEqual(100)
     */
    private $length;
    /**
     * @var Search
     * @Assert\Valid()
     */
    private $search;
    /**
     * @var Order[]
     * @Assert\Valid()
     */
    private $order = [];
    /**
     * @var Column[]
     * @Assert\Valid()
     */
    private $columns = [];

    /**
     * @param int $draw
     * @param int $start
     * @param int $length
     */
    public function __construct(int $draw, int $start, int $length)
    {
        $this->draw = $draw;
        $this->start = $start;
        $this->length = $length;
    }

    /**
     * @param Search $search
     * @return Request
     */
    public function setSearch(Search $search): Request
    {
        $this->search = $search;
        return $this;
    }

    /**
     * @param Order $order
     * @return Request
     */
    public function addOrder(Order $order): Request
    {
        $this->order[] = $order;
        return $this;
    }

    /**
     * @param Column $column
     * @return Request
     */
    public function addColumn(Column $column): Request
    {
        $this->columns[$column->getId()] = $column;
        return $this;
    }

    /**
     * @return int
     */
    public function getDraw(): int
    {
        return $this->draw;
    }

    /**
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return Search|null
     */
    public function getSearch(): ?Search
    {
        return $this->search;
    }

    /**
     * @return bool
     */
    public function hasSearch(): bool
    {
        return !is_null($this->search) && $this->search->isValid();
    }

    /**
     * @return Order[]
     */
    public function getOrders(): array
    {
        return $this->order;
    }

    /**
     * @param int $id
     * @return Column
     */
    public function getColumn(int $id): Column
    {
        return $this->columns[$id];
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function jsonSerialize()
    {
        return [
            'start' => $this->start,
            'length' => $this->length,
            'search' => $this->search,
            'order' => $this->order,
            'columns' => $this->columns
        ];
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return sha1(json_encode($this));
    }
}

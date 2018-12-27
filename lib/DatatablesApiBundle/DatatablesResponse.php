<?php

namespace DatatablesApiBundle;

class DatatablesResponse implements \JsonSerializable
{
    /** @var int */
    private $draw = 0;
    /** @var int */
    private $recordsTotal = 0;
    /** @var int */
    private $recordsFiltered = 0;
    /** @var array */
    private $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getDraw(): int
    {
        return $this->draw;
    }

    /**
     * @param int $draw
     * @return DatatablesResponse
     */
    public function setDraw(int $draw): DatatablesResponse
    {
        $this->draw = $draw;
        return $this;
    }

    /**
     * @return int
     */
    public function getRecordsTotal(): int
    {
        return $this->recordsTotal;
    }

    /**
     * @param int $recordsTotal
     * @return DatatablesResponse
     */
    public function setRecordsTotal(int $recordsTotal): DatatablesResponse
    {
        $this->recordsTotal = $recordsTotal;
        return $this;
    }

    /**
     * @return int
     */
    public function getRecordsFiltered(): int
    {
        return $this->recordsFiltered;
    }

    /**
     * @param int $recordsFiltered
     * @return DatatablesResponse
     */
    public function setRecordsFiltered(int $recordsFiltered): DatatablesResponse
    {
        $this->recordsFiltered = $recordsFiltered;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return DatatablesResponse
     */
    public function setData(array $data): DatatablesResponse
    {
        $this->data = $data;
        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'draw' => $this->getDraw(),
            'recordsTotal' => $this->getRecordsTotal(),
            'recordsFiltered' => $this->getRecordsFiltered(),
            'data' => $this->getData(),
        ];
    }
}

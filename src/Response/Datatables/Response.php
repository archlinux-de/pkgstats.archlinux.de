<?php

namespace App\Response\Datatables;

class Response implements \JsonSerializable
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
     * @return Response
     */
    public function setDraw(int $draw): Response
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
     * @return Response
     */
    public function setRecordsTotal(int $recordsTotal): Response
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
     * @return Response
     */
    public function setRecordsFiltered(int $recordsFiltered): Response
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
     * @return Response
     */
    public function setData(array $data): Response
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

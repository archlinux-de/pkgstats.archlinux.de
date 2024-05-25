<?php

namespace App\Entity;

interface PopularityListInterface
{
    public function getTotal(): int;

    public function getCount(): int;

    public function getLimit(): int;

    public function getOffset(): int;

    public function getQuery(): ?string;
}

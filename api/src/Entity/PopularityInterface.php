<?php

namespace App\Entity;

interface PopularityInterface
{
    public function getSamples(): int;

    public function getCount(): int;

    public function getPopularity(): float;

    public function getStartMonth(): int;

    public function getEndMonth(): int;
}

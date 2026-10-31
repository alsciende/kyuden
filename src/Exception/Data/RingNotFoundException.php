<?php

declare(strict_types=1);

namespace App\Exception\Data;

class RingNotFoundException extends NotFoundException
{
    public function __construct(string $element)
    {
        parent::__construct("Ring not found: [{$element}]");
    }
}

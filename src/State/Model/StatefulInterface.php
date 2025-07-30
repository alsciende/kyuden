<?php

declare(strict_types=1);

namespace App\State\Model;

interface StatefulInterface
{
    public function getId(): string;

    public function getState(): string;

    public function setState(string $state): void;
}

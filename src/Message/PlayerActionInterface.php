<?php

declare(strict_types=1);

namespace App\Message;

interface PlayerActionInterface extends ActionInterface
{
    public function getPlayerId(): string;
}

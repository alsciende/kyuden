<?php

namespace App\Event;

interface FrameworkEventInterface
{
    public function getGameId(): string;
}
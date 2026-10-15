<?php

declare(strict_types=1);

namespace App\Exception\Data;

class UserNotFoundException extends NotFoundException
{
    public function __construct(
        private readonly string $userId,
    ) {
        parent::__construct("User not found: [{$this->userId}]");
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}

<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction(public: false)]
final readonly class CreateGame implements ActionInterface
{
    public function __construct(
        private string $gameId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }
}

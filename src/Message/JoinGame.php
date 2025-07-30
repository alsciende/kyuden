<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppActionMessage;
use Symfony\Component\Uid\Uuid;

#[AppActionMessage]
final readonly class JoinGame implements PlayerActionInterface
{
    /**
     * @param array<string, string> $cardIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $userId,
        private string $userName,
        private string $deckId,
        private ?string $deckName,
        private array $cardIds,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getDeckId(): string
    {
        return $this->deckId;
    }

    public function getDeckName(): ?string
    {
        return $this->deckName;
    }

    /**
     * @return array<string, string>
     */
    public function getCardIds(): array
    {
        return $this->cardIds;
    }

    /**
     * @param array<string> $deckContent
     *
     * @return array<string, string>
     */
    public static function generateCardsIds(array $deckContent): array
    {
        $cardsIds = [];
        foreach ($deckContent as $id) {
            $cardsIds[Uuid::v4()->toString()] = $id;
        }

        return $cardsIds;
    }
}

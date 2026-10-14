<?= "<?php\n" ?>

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * TODO describe the message for the clients of the API (JSON schema); describe each field with #[Description] and
 * constrain its values with #[Assert\…], then run bin/console app:message-schemas:dump.
 */
#[AppAction]
final readonly class <?= $class_name ?> implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
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
}

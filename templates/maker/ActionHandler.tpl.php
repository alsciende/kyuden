<?= "<?php\n" ?>

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\<?= $action_class_name ?>;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class <?= $class_name . "\n" ?>
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
    ) {
    }

    public function __invoke(<?= $action_class_name ?> $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
    }
}

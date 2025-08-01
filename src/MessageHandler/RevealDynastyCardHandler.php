<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RevealDynastyCard;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class RevealDynastyCardHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private CardRepository $cardRepository,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    public function __invoke(RevealDynastyCard $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->dynastyCardStateMachine->apply(
            $card,
            'turn_faceup'
        );
    }
}

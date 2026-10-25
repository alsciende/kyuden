<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:game:transition',
    description: 'Add a short description for your command',
)]
readonly class GameTransitionCommand
{
    public function __construct(
        private GameRepository $repository,
        #[Target('game')]
        private WorkflowInterface $gameStateMachine,
    ) {
    }

    public function __invoke(
        #[Argument('Game ID')]
        string $gameId,
        #[Argument('Transition name')]
        string $transitionName,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $game = $this->repository->find($gameId);
        if (! $game instanceof Game) {
            throw new \RuntimeException("Game {$gameId} not found.");
        }
        $this->gameStateMachine->apply($game, $transitionName);

        return Command::SUCCESS;
    }
}

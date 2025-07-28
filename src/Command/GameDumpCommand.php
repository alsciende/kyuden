<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\GameStateManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:game:dump',
    description: 'Add a short description for your command',
)]
readonly class GameDumpCommand
{
    public function __construct(
        private GameRepository $repository,
        private WorkflowInterface $gameStateMachine,
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(
        #[Argument('Game ID')]
        string $id,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $game = $this->repository->find($id);
        if (! $game instanceof Game) {
            throw new \RuntimeException("Game {$id} not found.");
        }
        $io->section('State');
        $state = $this->stateManager->getState($game);
        dump($game);
        $io->text('');
        $io->section('Workflow');
        $io->text('Current Place: ' . $game->getCurrentPlace());
        $io->text('Enabled Transitions:');
        $transitions = $this->gameStateMachine->getEnabledTransitions($game);
        $io->listing(array_map(
            fn (Transition $transition): string => $transition->getName(),
            $transitions
        ));

        return Command::SUCCESS;
    }
}

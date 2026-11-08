<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\State\Manager\GameStateManager;
use App\View\GameInProgressViewFactory;
use App\View\Model\GameInProgressPlayerView;
use App\View\Model\GameInProgressView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:list',
    description: 'List the games in progress (started, not ended), the last started first; those that have ended too with --ended',
)]
readonly class GameListCommand
{
    public function __construct(
        private GameInProgressViewFactory $gameInProgressViewFactory,
        private GameRepository $gameRepository,
        private GameStateManager $gameStateManager,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only the games of this user (user ID or username)')]
        ?string $user = null,
        #[Option('The games that have ended too, with their winner')]
        bool $ended = false,
    ): int {
        $games = $this->gameRepository->findStarted($ended, $user === null ? null : $this->findUser($user)->getId());
        $results = [];
        foreach ($games as $game) {
            $results[$game->getId()] = $this->describeResult($game);
        }

        $headers = ['Id', 'Version', 'Place', 'Friendly', 'Players', 'Spectators'];
        $io->table(
            $ended ? [...$headers, 'Winner'] : $headers,
            array_map(
                fn (GameInProgressView $game): array => [
                    $game->id,
                    $game->version,
                    $game->place,
                    $game->friendly ? 'yes' : 'no',
                    implode(' vs ', array_map($this->describePlayer(...), $game->players)),
                    $game->spectators,
                    ...($ended ? [$results[$game->id]] : []),
                ],
                $this->gameInProgressViewFactory->createAll($games),
            ),
        );

        return Command::SUCCESS;
    }

    private function findUser(string $user): User
    {
        return $this->entityManager->find(User::class, $user)
            ?? $this->userRepository->findOneByUsername($user)
            ?? throw new \RuntimeException("User {$user} not found.");
    }

    private function describePlayer(GameInProgressPlayerView $player): string
    {
        $details = array_filter([$player->clan?->value, $player->bot ? 'bot' : null]);

        return sprintf('%s [%s]%s', $player->name, $player->userId, $details === [] ? '' : ' (' . implode(', ', $details) . ')');
    }

    /**
     * The winner of a game that has ended and how they won ("—" when nobody won), nothing while it goes on.
     */
    private function describeResult(Game $game): string
    {
        if ($game->getCurrentPlace() !== Game::STATE_ENDED) {
            return '';
        }

        $state = $this->gameStateManager->getState($game);
        $winner = $state->winnerId === null ? '—' : $game->getPlayer($state->winnerId)->getUser()->getName();

        return $state->victoryCondition === null ? $winner : "{$winner} ({$state->victoryCondition->value})";
    }
}

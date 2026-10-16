<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Repository\GameRepository;
use App\View\Model\UserGameView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The authenticated user: their identifier, and their games (in progress or ended, the last joined first), so that a
 * user matched by the queue finds the id of their game.
 */
class ApiSelfController extends AbstractController
{
    public function __construct(
        private readonly GameRepository $gameRepository,
    ) {
    }

    #[Route('/api/self', name: 'app_api_self')]
    public function index(): JsonResponse
    {
        $user = $this->getUser();
        if ($user instanceof UserInterface === false) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'user' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/api/self/games', name: 'app_api_self_games', methods: ['GET'])]
    public function games(#[CurrentUser] UserInterface $user): JsonResponse
    {
        $userId = $user->getUserIdentifier();

        return $this->json(array_map(
            fn (Game $game): UserGameView => $this->createView($game, $userId),
            $this->gameRepository->findByUser($userId),
        ));
    }

    private function createView(Game $game, string $userId): UserGameView
    {
        $players = $game->getPlayers()->getValues();
        $player = array_find($players, fn (Player $player): bool => $player->getUserId() === $userId)
            ?? throw new \LogicException("The user {$userId} does not play the game {$game->getId()}.");

        return new UserGameView(
            id: $game->getId(),
            playerId: $player->getId(),
            version: $game->getVersion(),
            ended: $game->getCurrentPlace() === Game::STATE_ENDED,
            players: array_map(fn (Player $player): string => $player->getUserId(), $players),
        );
    }
}

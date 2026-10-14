<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GameRepository;
use App\View\GameViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The state of a game as the authenticated player sees it (see GameViewFactory). A read goes neither through the bus
 * nor through the lock of the game. Errors are problem details (RFC 9457).
 */
class ApiGamesController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameViewFactory $gameViewFactory,
    ) {
    }

    #[Route('/api/games/{id}', name: 'app_api_game', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] UserInterface $user): Response
    {
        $game = $this->gameRepository->find($id);
        if ($game === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $user->getUserIdentifier()) {
                return $this->json($this->gameViewFactory->create($game, $player));
            }
        }

        return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
    }
}

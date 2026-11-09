<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\Api\ApiProblemException;
use App\Repository\GameRepository;
use App\Service\Abandonment\AbandonmentClaimer;
use App\Service\Abandonment\PlayerPresence;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * A player whose opponent no longer plays ends the game, and wins (see AbandonmentClaimer): whom the game waits for
 * and from when the player may end it, then ending it. For the players of the game only. Errors are problem details
 * (RFC 9457).
 */
class ApiAbandonmentController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly AbandonmentClaimer $abandonmentClaimer,
        private readonly PlayerPresence $playerPresence,
    ) {
    }

    /**
     * Depends on the time and on whether the opponent still reads the game, not on its version only: never stored.
     */
    #[Route('/api/games/{id}/abandonment', name: 'app_api_game_abandonment', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] User $user): Response
    {
        $game = $this->gameRepository->findWithCards($id);
        // a replay is never abandoned: it waits for nobody
        if ($game === null || $game->isReplay()) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'game_not_found', "Game not found: [{$id}]");
        }

        $player = $this->abandonmentClaimer->findPlayer($game, $user->getId());
        if ($player === null) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'not_a_player', 'The user is not a player of this game.');
        }

        $this->playerPresence->see($player);
        $response = $this->json($this->abandonmentClaimer->getView($game, $player));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/api/games/{id}/abandonment', name: 'app_api_game_abandonment_claim', methods: ['POST'])]
    public function claim(string $id, #[CurrentUser] User $user): Response
    {
        try {
            $version = $this->abandonmentClaimer->claim($id, $user->getId());
        } catch (ApiProblemException $problem) {
            return $this->problem($problem->getStatus(), $problem->getType(), $problem->getMessage(), $problem->getExtensions());
        }

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->setEtag((string) $version);

        return $response;
    }
}

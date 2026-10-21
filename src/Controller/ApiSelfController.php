<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Repository\GameRepository;
use App\View\Model\SelfView;
use App\View\Model\UserGameView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The authenticated user: their identifier, their name at court (which they change, `PATCH {"name": ...}`) and their
 * username, and their games (in progress or ended, the last joined first), so that a user matched by the queue finds
 * the id of their game. Errors are problem details (RFC 9457).
 */
class ApiSelfController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/self', name: 'app_api_self', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(new SelfView($user->getId(), $user->getName(), $user->getUsername()));
    }

    #[Route('/api/self', name: 'app_api_self_update', methods: ['PATCH'])]
    public function update(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $name = $body['name'] ?? null;
        if (! is_string($name) || array_diff(array_keys($body), ['name']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "name".', [
                'violations' => [['property_path' => 'name', 'message' => 'This field must be a string.']],
            ]);
        }

        $name = trim($name);
        $violations = $this->validator->startContext()
            ->atPath('name')
            ->validate($name, [new Assert\NotBlank(), new Assert\Length(max: User::MAX_NAME_LENGTH)])
            ->getViolations();
        if ($violations->count() > 0) {
            return $this->validationProblem($violations);
        }

        $user->rename($name);
        $this->entityManager->flush();

        return $this->index($user);
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

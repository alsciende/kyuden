<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\Clan;
use App\Model\AccountRequest;
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
 * The authenticated user: their identifier, their name at court and their preferred clan (which they change,
 * `PATCH {"name": ..., "preferred_clan": ...}`, either or both) and their username, and their games (in progress or ended, the last joined first), so that a user matched by the queue finds
 * the id of their game. Errors are problem details (RFC 9457).
 */
class ApiSelfController extends AbstractController
{
    use ProblemResponseTrait;

    private const array UPDATABLE_FIELDS = ['name', 'preferred_clan'];

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/self', name: 'app_api_self', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(new SelfView($user->getId(), $user->getName(), $user->getUsername(), $user->getPreferredClan()));
    }

    #[Route('/api/self', name: 'app_api_self_update', methods: ['PATCH'])]
    public function update(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $structureViolations = [];
        foreach (array_diff(array_map(strval(...), array_keys($body)), self::UPDATABLE_FIELDS) as $field) {
            $structureViolations[] = ['property_path' => $field, 'message' => 'This field is not expected.'];
        }
        foreach (self::UPDATABLE_FIELDS as $field) {
            if (array_key_exists($field, $body) && ! is_string($body[$field])) {
                $structureViolations[] = ['property_path' => $field, 'message' => 'This field must be a string.'];
            }
        }
        if ($body === []) {
            $structureViolations[] = ['property_path' => '', 'message' => sprintf('The body must have one of: %s.', implode(', ', self::UPDATABLE_FIELDS))];
        }
        if ($structureViolations !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Invalid changes of the user.', ['violations' => $structureViolations]);
        }
        /** @var array{name?: string, preferred_clan?: string} $body */
        $name = isset($body['name']) ? trim($body['name']) : null;
        $clan = $body['preferred_clan'] ?? null;

        $validator = $this->validator->startContext();
        if ($name !== null) {
            $validator->atPath('name')->validate($name, [new Assert\NotBlank(), new Assert\Length(max: User::MAX_NAME_LENGTH)]);
        }
        if ($clan !== null) {
            $validator->atPath('preferred_clan')->validate($clan, new Assert\Choice(
                choices: AccountRequest::getClanValues(),
                message: 'Choose one of the Great Clans: {{ choices }}.',
            ));
        }
        $violations = $validator->getViolations();
        if ($violations->count() > 0) {
            return $this->validationProblem($violations);
        }

        if ($name !== null) {
            $user->rename($name);
        }
        if ($clan !== null) {
            $user->preferClan(Clan::from($clan));
        }
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

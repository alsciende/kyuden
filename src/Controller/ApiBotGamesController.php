<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\UserType;
use App\Repository\DeckRepository;
use App\Service\Bot\HouseBot;
use App\Service\GameLauncher\BotGameLauncher;
use App\View\Model\BotGameOpponentView;
use App\View\Model\BotGameView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Games against the house bot (HouseBot), without the queue: the authenticated user plays one of the decks they can use
 * (`{"deck_id": ...}`), the bot plays another starter deck, drawn at random. The game starts at once; the bot plays
 * once the response is sent. Errors are problem details (RFC 9457).
 */
class ApiBotGamesController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly DeckRepository $deckRepository,
        private readonly BotGameLauncher $botGameLauncher,
        private readonly HouseBot $houseBot,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/bot-games', name: 'app_api_bot_games_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $deckId = $body['deck_id'] ?? null;
        if (! is_string($deckId) || array_diff(array_keys($body), ['deck_id']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "deck_id".', [
                'violations' => [['property_path' => 'deck_id', 'message' => 'The id of a deck of the user, or of a starter deck.']],
            ]);
        }

        $deck = $this->deckRepository->findUsable($deckId, $user);
        if (! $deck instanceof Deck) {
            return $this->problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'deck_not_found', "Deck not found: [{$deckId}]");
        }

        $bot = $this->entityManager->find(User::class, $this->houseBot->getUserId());
        $botDecks = array_values(array_filter(
            $this->deckRepository->findWithoutOwner(),
            fn (Deck $starterDeck): bool => $starterDeck->getId() !== $deck->getId(),
        ));
        if (! $bot instanceof User || $bot->getType() !== UserType::Bot || $botDecks === []) {
            return $this->problem(Response::HTTP_SERVICE_UNAVAILABLE, 'bot_unavailable', 'No bot can play right now.');
        }
        // the deck of the bot is drawn before the game exists: the event of the bot joining the game records it
        $botDeck = $botDecks[new \Random\Randomizer()->getInt(0, count($botDecks) - 1)];

        [$gameId, $playerId] = $this->botGameLauncher->launch($user, $deck, $bot, $botDeck);

        return $this->json(
            new BotGameView($gameId, $playerId, new BotGameOpponentView($bot->getId(), $bot->getName(), $botDeck->getId())),
            Response::HTTP_CREATED,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\RoleCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\LogicalCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\ConflictType;
use App\Enum\DuelType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Data\DeckNotFoundException;
use App\Exception\Data\LogicalCardNotFoundException;
use App\Repository\DeckRepository;
use App\Repository\LogicalCardRepository;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\ConflictState;
use App\State\Model\DuelState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Builds a game directly in a given state, without dispatching any action.
 *
 * The workflow place of the game is written directly: no transition, guard or listener is triggered.
 * Cards are put directly in their stack, unless stated otherwise: in a deck or a province stack with the initial
 * orientation of their type (see PhysicalCard::getInitialOrientation()), face up elsewhere.
 *
 * Players get all their unique stacks once the game has started (any place but join_game).
 *
 * Use build() for code that works on entities (guards, listeners called directly) and persist() for
 * code that loads entities by id (message handlers).
 */
final class GameBuilder
{
    private string $place = 'join_game';

    private ?string $firstPlayer = null;

    /**
     * @var list<string>
     */
    private array $activePlayers = [];

    /**
     * @var array<string, PlayerBuilder>
     */
    private array $players = [];

    /**
     * Applied to the game state once the players and cards are built.
     *
     * @var list<\Closure(GameState, Scenario): void>
     */
    private array $stateChanges = [];

    /**
     * @var list<Stack>
     */
    private array $builtStacks = [];

    /**
     * @var array<string, PhysicalCard>
     */
    private array $builtCards = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LogicalCardRepository $logicalCardRepository,
        private readonly DeckRepository $deckRepository,
        private readonly GameStateManager $gameStateManager,
        private readonly PlayerStateManager $playerStateManager,
        private readonly CardStateManager $cardStateManager,
    ) {
    }

    public function atPlace(string $place): self
    {
        $this->place = $place;

        return $this;
    }

    /**
     * @param \Closure(PlayerBuilder): mixed $configure
     */
    public function withPlayer(string $alias, ?\Closure $configure = null): self
    {
        $player = new PlayerBuilder($alias);
        if ($configure !== null) {
            $configure($player);
        }
        $this->players[$alias] = $player;

        return $this;
    }

    /**
     * Defaults to the first declared player once the game has started.
     */
    public function firstPlayer(string $alias): self
    {
        $this->firstPlayer = $alias;

        return $this;
    }

    /**
     * Player who has the opportunity to act. None by default.
     */
    public function activePlayer(string $alias): self
    {
        return $this->activePlayers($alias);
    }

    /**
     * Players who have the opportunity to act simultaneously.
     */
    public function activePlayers(string ...$aliases): self
    {
        $this->activePlayers = array_values($aliases);

        return $this;
    }

    /**
     * Fate on a ring and the player who claimed it (by alias).
     */
    public function ring(Element $element, int $fate = 0, ?string $claimedBy = null): self
    {
        $this->stateChanges[] = function (GameState $state, Scenario $scenario) use ($element, $fate, $claimedBy): void {
            $state->getRing($element)->fate = $fate;
            $state->getRing($element)->claimedById = $claimedBy === null ? null : $scenario->player($claimedBy)->getId();
        };

        return $this;
    }

    /**
     * A conflict declared by a player against a province (aliases); the defending player owns the province.
     *
     * @param list<string> $attackers aliases of the attacking characters
     * @param list<string> $defenders aliases of the defending characters
     */
    public function conflict(
        string $attacker,
        string $province,
        array $attackers = [],
        array $defenders = [],
        Element $ring = Element::Air,
        ConflictType $type = ConflictType::Military,
    ): self {
        $this->stateChanges[] = function (GameState $state, Scenario $scenario) use ($attacker, $province, $attackers, $defenders, $ring, $type): void {
            $provinceCard = $scenario->card($province);
            $state->conflict = new ConflictState($scenario->player($attacker)->getId(), $provinceCard->getPlayer()->getId(), $ring, $type, $provinceCard->getId());
            $state->conflict->attackerIds = array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $attackers);
            $state->conflict->defenderIds = array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $defenders);
        };

        return $this;
    }

    /**
     * A duel initiated by a player between characters (aliases); the challenged player controls the challenged
     * characters. `revealed`: the honor dials of the duel have been revealed.
     *
     * @param list<string> $challengers aliases of the characters of the challenger
     * @param list<string> $challenged  aliases of the characters of the challenged player
     */
    public function duel(
        string $challenger,
        array $challengers,
        array $challenged,
        DuelType $type = DuelType::Military,
        bool $revealed = false,
    ): self {
        $this->stateChanges[] = function (GameState $state, Scenario $scenario) use ($challenger, $challengers, $challenged, $type, $revealed): void {
            $challengedPlayer = $scenario->card($challenged[0] ?? throw new \LogicException('A duel needs a challenged character.'))->getPlayer();
            $state->duel = new DuelState($scenario->player($challenger)->getId(), $challengedPlayer->getId(), $type);
            $state->duel->challengerCardIds = array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $challengers);
            $state->duel->challengedCardIds = array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $challenged);
            $state->duel->revealed = $revealed;
        };

        return $this;
    }

    /**
     * Changes the game state directly, once the players and cards are built.
     *
     * @param \Closure(GameState, Scenario): void $change
     */
    public function withGameState(\Closure $change): self
    {
        $this->stateChanges[] = $change;

        return $this;
    }

    public function build(): Scenario
    {
        $this->builtStacks = [];
        $this->builtCards = [];

        $game = new Game(Uuid::v4()->toString());
        $game->setCurrentPlace($this->place);

        $players = [];
        foreach ($this->players as $alias => $playerBuilder) {
            $players[$alias] = $this->buildPlayer($game, $playerBuilder);
        }

        // once the game has started, the player order is the order in which players are declared, and the rings are unclaimed
        if ($this->place !== 'join_game') {
            $this->gameStateManager->withState($game, fn (GameState $state): GameState => $state->setPlayerOrder(...array_values($players))->initRings());
        }

        $firstPlayer = $this->firstPlayer ?? ($this->place === 'join_game' ? null : array_key_first($players));
        if ($firstPlayer !== null) {
            $player = $players[$firstPlayer] ?? throw new \OutOfBoundsException("No player aliased '{$firstPlayer}'.");
            $this->gameStateManager->withState($game, fn (GameState $state): GameState => $state->setFirstPlayer($player));
        }

        if ($this->activePlayers !== []) {
            $activePlayerIds = array_map(
                fn (string $alias): string => ($players[$alias] ?? throw new \OutOfBoundsException("No player aliased '{$alias}'."))->getId(),
                $this->activePlayers,
            );
            $this->gameStateManager->withState($game, function (GameState $state) use ($activePlayerIds): GameState {
                $state->activePlayerIds = $activePlayerIds;

                return $state;
            });
        }

        $scenario = new Scenario($game, $players, $this->builtCards);
        foreach ($this->stateChanges as $change) {
            $this->gameStateManager->withState($game, fn (GameState $state) => $change($state, $scenario));
        }

        return $scenario;
    }

    public function persist(): Scenario
    {
        $scenario = $this->build();

        $this->entityManager->persist($scenario->game());
        foreach ($scenario->game()->getPlayers() as $player) {
            $this->entityManager->persist($player);
        }
        foreach ($this->builtStacks as $stack) {
            $this->entityManager->persist($stack);
        }
        foreach ($scenario->game()->getPhysicalCards() as $card) {
            $this->entityManager->persist($card);
        }
        $this->entityManager->flush();

        return $scenario;
    }

    private function buildPlayer(Game $game, PlayerBuilder $builder): Player
    {
        $player = new Player(Uuid::v4()->toString());
        $player->setUserId($builder->alias);
        $player->setDeck($this->getDeck($builder->getDeckId() ?? AppFixtures::LION_STARTER_DECK));
        $game->addPlayer($player);

        // once the game has started, every player has all its unique stacks, even empty
        $uniqueStacks = [];
        if ($game->getCurrentPlace() !== 'join_game') {
            foreach (StackType::cases() as $type) {
                if ($type->isUnique()) {
                    $uniqueStacks[$type->value] = $this->createStack($player, $type);
                }
            }
        }

        $starterDeckId = $builder->getStarterDeckId();
        if ($starterDeckId !== null) {
            $this->layOutStarterDeck($player, $uniqueStacks, $starterDeckId, $builder->getStarterDeckAliases());
        }

        $strongholdProvinceId = null;
        foreach ($builder->getStacks() as $plannedStack) {
            $stack = $uniqueStacks[$plannedStack->type->value] ?? $this->createStack($player, $plannedStack->type);
            foreach ($plannedStack->cards as $plannedCard) {
                $card = $this->createCard($player, $stack, $plannedCard);
                if ($plannedCard->chosenStrongholdProvince) {
                    $strongholdProvinceId = $card->getId();
                }
            }
        }

        $chosenAlias = $builder->getChosenStrongholdProvince();
        if ($chosenAlias !== null) {
            $province = $this->builtCards[$chosenAlias] ?? throw new \OutOfBoundsException("No card aliased '{$chosenAlias}'.");
            $strongholdProvinceId = $province->getId();
        }

        $this->initPlayerState($player, $builder, $strongholdProvinceId);

        return $player;
    }

    /**
     * @param array<int, Stack>     $uniqueStacks indexed by stack type
     * @param array<string, string> $aliases      alias => card code
     */
    private function layOutStarterDeck(Player $player, array $uniqueStacks, string $deckId, array $aliases): void
    {
        $aliasesByCode = array_flip($aliases);
        foreach ($this->getDeck($deckId)->getContent() as $code) {
            $stackType = match ($this->getClassname($this->getLogicalCard($code))) {
                ConflictCard::class => StackType::ConflictDrawDeck,
                DynastyCard::class => StackType::DynastyDrawDeck,
                RoleCard::class => StackType::Role,
                ProvinceCard::class, StrongholdCard::class => StackType::StrongholdProvince,
                default => throw new \LogicException("Unexpected card class for {$code}."),
            };
            $stack = $uniqueStacks[$stackType->value] ?? throw new \LogicException('A starter deck can only be laid out once the game has started.');
            $this->createCard($player, $stack, new PlannedCard($code, $aliasesByCode[$code] ?? null));
            unset($aliasesByCode[$code]);
        }
    }

    private function initPlayerState(Player $player, PlayerBuilder $builder, ?string $strongholdProvinceId): void
    {
        $honor = $builder->getHonor();
        $this->playerStateManager->withState($player, function (PlayerState $state) use ($builder, $honor, $strongholdProvinceId): void {
            $state->setFate($builder->getFate())
                ->setFateCollected($builder->isFateCollected())
                ->setHonorBid($builder->getHonorBid())
                ->setPendingHonorBid($builder->getPendingHonorBid())
                ->setPassed($builder->isPassed())
                ->setConflictOpportunities($builder->getConflictOpportunities())
                ->setDeclaredConflictTypes($builder->getDeclaredConflictTypes());
            if ($honor !== null) {
                $state->setHonor($honor);
            }
            if ($strongholdProvinceId !== null) {
                $state->setStrongholdProvinceId($strongholdProvinceId);
            }
        });
    }

    private function createStack(Player $player, StackType $type): Stack
    {
        $stack = new Stack(Uuid::v4()->toString(), $player, $type);
        $this->builtStacks[] = $stack;

        return $stack;
    }

    /**
     * Null for the initial orientation of the card type (face down, except the stronghold).
     */
    private function getDefaultOrientation(Stack $stack): ?Orientation
    {
        return match ($stack->getType()) {
            StackType::ConflictDrawDeck, StackType::DynastyDrawDeck, StackType::StrongholdProvince, StackType::RegularProvince => null,
            StackType::ConflictDiscardPile, StackType::DynastyDiscardPile, StackType::Role, StackType::Hand, StackType::InPlay => Orientation::FaceUp,
        };
    }

    private function createCard(Player $player, Stack $stack, PlannedCard $plannedCard): PhysicalCard
    {
        $logicalCard = $this->getLogicalCard($plannedCard->code);
        $classname = $this->getClassname($logicalCard);

        $card = new $classname(Uuid::v4()->toString(), $logicalCard, $stack, $plannedCard->orientation ?? $this->getDefaultOrientation($stack));
        $card->setTitle($logicalCard->getName());
        // fails if the card cannot be in that stack with that orientation
        $card->getCurrentPlace();
        $stack->addCard($card);
        $player->addCard($card);
        $player->getGame()->addCard($card);
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->setFate($plannedCard->fate)->setPersonalHonor($plannedCard->personalHonor),
        );

        if ($plannedCard->alias !== null) {
            if (isset($this->builtCards[$plannedCard->alias])) {
                throw new \LogicException("Card alias '{$plannedCard->alias}' is used twice.");
            }
            $this->builtCards[$plannedCard->alias] = $card;
        }

        return $card;
    }

    /**
     * @return class-string<PhysicalCard>
     */
    private function getClassname(LogicalCard $logicalCard): string
    {
        return PhysicalCard::DISCRIMINATOR_MAP[$logicalCard->getSide()->value];
    }

    private function getLogicalCard(string $code): LogicalCard
    {
        return $this->logicalCardRepository->find($code) ?? throw new LogicalCardNotFoundException($code);
    }

    private function getDeck(string $deckId): Deck
    {
        return $this->deckRepository->find($deckId) ?? throw new DeckNotFoundException($deckId);
    }
}

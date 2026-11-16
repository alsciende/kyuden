<?php

declare(strict_types=1);

namespace App\Service\Trophy;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Trophy;
use App\Enum\UserType;
use App\State\Manager\GameStateManager;
use App\Trophy\TrophyInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The trophies the players of a game that has ended earn in it: each trophy has its rule (`src/Trophy`, a
 * TrophyInterface), read from what is stored, so that the games played before earn them too. Only humans (guests
 * included) earn trophies; a replay earns nothing.
 */
readonly class GameTrophies
{
    /**
     * @param iterable<TrophyInterface> $rules
     */
    public function __construct(
        private GameStateManager $gameStateManager,
        private TrophyCase $trophyCase,
        #[AutowireIterator(TrophyInterface::class)]
        private iterable $rules,
    ) {
    }

    /**
     * @return list<array{Player, Trophy}> the trophies each player earns in the game, earned before or not, player by
     *                                     player in the order of the catalogue
     */
    public function of(Game $game): array
    {
        if ($game->isReplay()) {
            return [];
        }

        $state = $this->gameStateManager->getState($game);
        $rules = [...$this->rules];
        $order = array_flip(array_map(static fn (Trophy $trophy): string => $trophy->value, Trophy::cases()));
        usort($rules, static fn (TrophyInterface $a, TrophyInterface $b): int => $order[$a::getTrophy()->value] <=> $order[$b::getTrophy()->value]);

        $trophies = [];
        foreach ($game->getPlayers() as $player) {
            if ($player->getUser()->getType() !== UserType::Human) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule->isEarnedBy($player, $game, $state)) {
                    $trophies[] = [$player, $rule::getTrophy()];
                }
            }
        }

        return $trophies;
    }

    /**
     * Awards the trophies of the game (persisted, not flushed).
     *
     * @return list<array{Player, Trophy}> the trophies earned for the first time
     */
    public function award(Game $game, \DateTimeImmutable $earnedAt): array
    {
        return array_values(array_filter(
            $this->of($game),
            fn (array $earned): bool => $this->trophyCase->award($earned[0]->getUser(), $earned[1], $earnedAt, $game),
        ));
    }
}

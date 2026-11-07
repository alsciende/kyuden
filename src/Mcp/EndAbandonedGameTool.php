<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Exception\Api\ApiProblemException;
use App\Service\Abandonment\AbandonmentClaimer;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * A bot ends a game its opponent no longer plays (see POST /api/games/{id}/abandonment).
 */
final readonly class EndAbandonedGameTool
{
    public function __construct(
        private AbandonmentClaimer $abandonmentClaimer,
        private Security $security,
    ) {
    }

    /**
     * Ends a game your opponent no longer plays: they have abandoned it, you win. Accepted once the game has waited for
     * your opponent only (not for you) for 3 minutes without changing, or for 1 minute when your opponent no longer
     * reads it; rejected before (game_not_abandoned, with the time from which it is accepted). Returns the version of
     * the game, which has ended.
     *
     * @param string $game_id the id of the game
     *
     * @return array{version: int}
     */
    #[McpTool(name: 'end_abandoned_game')]
    public function __invoke(string $game_id): array
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        \assert($userId !== null);

        try {
            return ['version' => $this->abandonmentClaimer->claim($game_id, $userId)];
        } catch (ApiProblemException $problem) {
            throw new ToolCallException(json_encode($problem->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), previous: $problem);
        }
    }
}

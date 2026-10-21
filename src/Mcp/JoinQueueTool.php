<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Service\MatchMaking\MatchMakingQueue;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * Joining the matchmaking queue (see POST /api/queue).
 */
final readonly class JoinQueueTool
{
    public function __construct(
        private MatchMakingQueue $queue,
        private Security $security,
    ) {
    }

    /**
     * Joins the matchmaking queue with one of your decks (list_decks): you are matched with the next player waiting,
     * at once if one waits already. A game is then created for you both: wait for it with wait_for_game.
     *
     * @param string $deck_id the id of a deck you can use
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'join_queue')]
    public function __invoke(string $deck_id): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        try {
            $this->queue->join($user, $deck_id);
        } catch (DeckNotFoundException|AlreadyQueuedException $exception) {
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        }

        return ['deck_id' => $deck_id];
    }
}

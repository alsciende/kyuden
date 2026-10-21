<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\User;
use App\Exception\Api\NotQueuedException;
use App\Service\MatchMaking\MatchMakingQueue;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * Leaving the matchmaking queue (see DELETE /api/queue).
 */
final readonly class LeaveQueueTool
{
    public function __construct(
        private MatchMakingQueue $queue,
        private Security $security,
    ) {
    }

    /**
     * Leaves the matchmaking queue. An error when you are not in it: you never joined it, or you have been matched
     * already (your game is in list_my_games).
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'leave_queue')]
    public function __invoke(): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        try {
            $this->queue->leave($user);
        } catch (NotQueuedException $exception) {
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        }

        return ['left' => true];
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\RulesException;
use App\MessageHandler\PlayerActionHandlerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The public actions a player can send now, checked by the handlers themselves (PlayerActionHandlerInterface), so that
 * the list and the handling cannot diverge. An action is listed when what it requires from the state is met: its
 * payload can still be rejected (a card that cannot be played, a ring already claimed…).
 */
final readonly class AvailableActionsResolver
{
    public function __construct(
        private ActionMessageRegistry $actionMessageRegistry,
        #[Autowire(service: '.app.player_action_handlers')]
        private ContainerInterface $handlers,
    ) {
    }

    /**
     * @return list<string> the types of the public messages, sorted; none once the game has ended (GameEndedMiddleware)
     */
    public function resolve(Game $game, Player $player): array
    {
        if ($game->getCurrentPlace() === Game::STATE_ENDED) {
            return [];
        }

        $types = [];
        foreach ($this->actionMessageRegistry->getPublicMessageClasses() as $type => $messageClass) {
            try {
                $this->getHandler($messageClass)->assertAvailable($game, $player);
                $types[] = $type;
            } catch (RulesException|WorkflowException) {
            }
        }

        return $types;
    }

    /**
     * @param class-string $messageClass
     */
    public function getHandler(string $messageClass): PlayerActionHandlerInterface
    {
        if (! $this->handlers->has($messageClass)) {
            throw new \LogicException(sprintf('The handler of %s does not implement %s.', $messageClass, PlayerActionHandlerInterface::class));
        }

        $handler = $this->handlers->get($messageClass);
        if (! $handler instanceof PlayerActionHandlerInterface) {
            throw new \LogicException(sprintf('The handler of %s does not implement %s.', $messageClass, PlayerActionHandlerInterface::class));
        }

        return $handler;
    }
}

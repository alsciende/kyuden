<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Service\Bot\HouseBot;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Once a message is handled, tells the house bot that its game has changed: the bot may have something to do in it,
 * once the response is sent (HouseBot).
 */
readonly class HouseBotMiddleware implements MiddlewareInterface
{
    public function __construct(
        private HouseBot $houseBot,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);
        $message = $envelope->getMessage();
        if ($message instanceof ActionInterface) {
            $this->houseBot->schedule($message->getGameId());
        }

        return $envelope;
    }
}

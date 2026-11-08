<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Middleware\Stamp\ReplayStamp;
use App\Service\Bot\HouseBot;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Once a message is handled (after the commit: first of the bus), tells the house bot that its game has changed: the
 * bot may have something to do in it, which the worker plays (HouseBot::wake()). It does not play in a replay
 * (ReplayStamp).
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
        if ($message instanceof ActionInterface && $envelope->last(ReplayStamp::class) === null) {
            $this->houseBot->wake($message->getGameId());
        }

        return $envelope;
    }
}

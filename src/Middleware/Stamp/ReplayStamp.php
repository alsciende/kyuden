<?php

declare(strict_types=1);

namespace App\Middleware\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The message replays an event of another game (Service/Replay/GameReplayer): nobody is notified and the house bot
 * does not play, the replay being a copy of a game that has ended.
 */
final readonly class ReplayStamp implements StampInterface
{
}

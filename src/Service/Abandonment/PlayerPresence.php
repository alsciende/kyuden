<?php

declare(strict_types=1);

namespace App\Service\Abandonment;

use App\Entity\Player;
use App\Repository\PlayerRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Whether a player still follows their game: they read it (its view, its log, whether it is abandoned, through the
 * HTTP API or the MCP tools). The web client reads a game at least every 15 seconds, an agent waits for its changes 30
 * seconds at most: a player who has not read it for ABSENT_AFTER seconds has left it (closed the page, stopped the
 * agent). Written at most every REFRESH seconds.
 */
final readonly class PlayerPresence
{
    public const int ABSENT_AFTER = 60;

    public const int REFRESH = 10;

    public function __construct(
        private PlayerRepository $playerRepository,
        private ClockInterface $clock,
    ) {
    }

    public function see(Player $player): void
    {
        $now = $this->clock->now();
        $this->playerRepository->see($player->getId(), $now, $now->modify('-' . self::REFRESH . ' seconds'));
    }
}

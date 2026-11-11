<?php

declare(strict_types=1);

namespace App\Service\Discord;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Clan;
use App\Enum\UserType;
use App\Repository\GameRepository;
use App\Task\AnnounceGame;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Announces the games that start on the official Discord server, in the channel of a webhook
 * (DISCORD_GAMES_WEBHOOK_URL, empty: no announcement), with the link to watch them: every game that can be watched (the
 * games against the house bot and the friendly games included, not the replays). Out of the request: the task
 * AnnounceGame of the worker, which a failure of Discord retries.
 */
final readonly class GameAnnouncer
{
    public function __construct(
        private GameRepository $gameRepository,
        private MessageBusInterface $taskBus,
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'DISCORD_GAMES_WEBHOOK_URL')]
        private string $webhookUrl,
        #[Autowire(env: 'SITE_URL')]
        private string $siteUrl,
    ) {
    }

    /**
     * Sends the announcement of a game that has just started to the worker, when the webhook is set.
     */
    public function schedule(Game $game): void
    {
        if ($this->webhookUrl !== '' && !$game->isReplay()) {
            $this->taskBus->dispatch(new AnnounceGame($game->getId()));
        }
    }

    /**
     * Posts the announcement of a game, unless it can no longer be watched (ended meanwhile, deleted).
     */
    public function announce(string $gameId): void
    {
        $game = $this->gameRepository->find($gameId);
        if ($this->webhookUrl === '' || !$game instanceof Game || $game->isReplay() || $game->getCurrentPlace() === Game::STATE_ENDED) {
            return;
        }

        $this->httpClient->request('POST', $this->webhookUrl, [
            'json' => $this->payload($game),
            'timeout' => 10,
        ])->getContent();
    }

    /**
     * @return array<string, mixed> the message of the webhook: an embed linking to the game, mentioning nobody (a
     *                              name at court may read "@everyone")
     */
    private function payload(Game $game): array
    {
        $players = $game->getPlayers()->getValues();
        $clans = $this->gameRepository->findStrongholdClans([$game->getId()]);
        $names = array_map(fn (Player $player): string => self::escape($player->getUser()->getName()), $players);
        $sides = array_map(fn (Player $player): string => $this->side($player, $clans[$player->getId()] ?? null), $players);

        $tags = [];
        if ($game->isFriendly()) {
            $tags[] = 'Friendly game';
        }

        return [
            'allowed_mentions' => ['parse' => []],
            'embeds' => [[
                'title' => implode(' vs ', $names),
                'url' => rtrim($this->siteUrl, '/') . '/games/' . $game->getId(),
                'description' => implode("\n", [implode(' against ', $sides), ...$tags]),
            ]],
        ];
    }

    private function side(Player $player, ?Clan $clan): string
    {
        $side = $clan instanceof Clan ? ucfirst($clan->value) : 'Unknown clan';

        return $player->getUser()->getType() === UserType::Bot ? $side . ' (bot)' : $side;
    }

    /**
     * Escapes the markdown of Discord in a name chosen by a player.
     */
    private static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\*_~`|>#\[\]()])/', '\\\\$1', $text);
    }
}

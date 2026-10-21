<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Repository\GameRepository;
use App\View\GameViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The state of a game as the bot sees it (see GET /api/games/{id}): the same view, masked for the bot.
 */
final readonly class GetGameTool
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameViewFactory $gameViewFactory,
        private NormalizerInterface $normalizer,
        private Security $security,
    ) {
    }

    /**
     * The state of a game as you see it: its version, the current step (`place`), the players in player order with
     * their honor, fate and stacks of cards (hidden stacks and face-down cards without their cards or codes), the
     * rings, the conflict and the duel being resolved, the Imperial Favor, the winner, and `available_actions`: the
     * actions (tools) you can send now. Cards are given by code: read them with get_cards. The ids of the cards of a
     * deck change when it is shuffled: take them from the latest state.
     *
     * @param string $game_id the id of the game
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'get_game')]
    public function __invoke(string $game_id): array
    {
        $game = $this->gameRepository->findWithCards($game_id);
        if ($game === null) {
            throw new ToolCallException("Game not found: [{$game_id}]");
        }

        $userId = $this->security->getUser()?->getUserIdentifier();
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $userId) {
                $view = $this->normalizer->normalize($this->gameViewFactory->create($game, $player));
                \assert(\is_array($view));

                return $view;
            }
        }

        throw new ToolCallException('You are not a player of this game.');
    }
}

<?php

declare(strict_types=1);

namespace App\Mcp;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * The doc comment of the method and its params are the description of the prompt.
 *
 * The prompt of a player: one line, how to play is told by the instructions of the server (config/packages/mcp.yaml).
 */
final readonly class PlayPrompt
{
    public const string PROMPT = 'Find your game on Kyūden and play it to the end.';

    /**
     * Play a game of Legend of the Five Rings on Kyūden: the game you play, else one found through the queue.
     *
     * @param ?string $deck the id of the deck to join the queue with (list_decks), if you have to
     *
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'play', title: 'Play a game')]
    public function __invoke(?string $deck = null): array
    {
        $prompt = self::PROMPT;
        if ($deck !== null && $deck !== '') {
            $prompt .= " If you have to join the queue, use the deck {$deck}.";
        }

        return [['role' => 'user', 'content' => $prompt]];
    }
}

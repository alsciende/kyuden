<?php

declare(strict_types=1);

namespace App\Mcp;

use Mcp\Capability\Attribute\McpResource;

/**
 * The doc comment of each method is the description of the resource the agents read.
 *
 * The official rules (`doc/`) as resources of the MCP server, so that an agent without a file system reads them. The
 * glossary is long: it is read entry by entry (ListGlossaryEntriesTool, GetGlossaryEntriesTool). The description of
 * the queue from the point of view of the app (`game.md`) and the glyphs of the icon font (`symbols.md`) are not
 * rules: they are not served.
 */
final readonly class RulesResources
{
    public const string URI_PREFIX = 'kyuden://rules/';

    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * The introductory rules: the game, its components, a round phase by phase, conflicts. Read it first if you do not
     * know the game.
     */
    #[McpResource(uri: self::URI_PREFIX . 'learn_to_play', name: 'learn_to_play', title: 'Learn to play', mimeType: 'text/markdown')]
    public function learnToPlay(): string
    {
        return $this->read('learn_to_play.md');
    }

    /**
     * The framework steps and the action windows of a round: the steps the game goes through, the same as the place of
     * the game (get_game).
     */
    #[McpResource(uri: self::URI_PREFIX . 'timing_and_gameplay', name: 'timing_and_gameplay', title: 'Timing and gameplay', mimeType: 'text/markdown')]
    public function timingAndGameplay(): string
    {
        return $this->read('timing-and-gameplay.md');
    }

    /**
     * The rulings on cards, by expansion and collector number.
     */
    #[McpResource(uri: self::URI_PREFIX . 'card_clarifications', name: 'card_clarifications', title: 'Card clarifications', mimeType: 'text/markdown')]
    public function cardClarifications(): string
    {
        return $this->read('card-clarifications.md');
    }

    private function read(string $file): string
    {
        $content = file_get_contents("{$this->projectDir}/doc/{$file}");
        if ($content === false) {
            throw new \RuntimeException(\sprintf('Cannot read the rules: %s', $file));
        }

        return $content;
    }
}

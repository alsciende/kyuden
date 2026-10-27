<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\Rules\Glossary;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * Entries of the glossary of the rules, several at once.
 */
final readonly class GetGlossaryEntriesTool
{
    public const int MAX_TERMS = 20;

    public function __construct(
        private Glossary $glossary,
    ) {
    }

    /**
     * Entries of the glossary of the rules, the source of truth on a term, a keyword or a situation of the game, by
     * their title (list_glossary_entries) or one of the terms it names (`bowed` reads `Bow, Bowed`), whatever the case.
     * An entry may end with the entries related to it ("Related").
     *
     * @param list<string> $terms the titles or terms of the entries
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'get_glossary_entries')]
    public function __invoke(
        #[Schema(items: ['type' => 'string'], minItems: 1, maxItems: self::MAX_TERMS)]
        array $terms,
    ): array {
        $entries = [];
        $unknown = [];
        foreach (array_unique($terms) as $term) {
            $entry = $this->glossary->find($term);
            if ($entry === null) {
                $unknown[] = $term;
            } else {
                $entries[$entry['title']] = $entry;
            }
        }

        if ($unknown !== []) {
            throw new ToolCallException(\sprintf('Glossary entry not found: [%s] (see list_glossary_entries)', implode('], [', $unknown)));
        }

        return ['entries' => array_values($entries)];
    }
}

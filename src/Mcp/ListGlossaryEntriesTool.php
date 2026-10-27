<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\Rules\Glossary;
use Mcp\Capability\Attribute\McpTool;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The titles of the entries of the glossary of the rules.
 */
final readonly class ListGlossaryEntriesTool
{
    public function __construct(
        private Glossary $glossary,
    ) {
    }

    /**
     * The titles of the entries of the glossary of the rules, in alphabetical order: every term, keyword and situation
     * of the game (`Bow, Bowed`, `Covert`, `Triggering Condition`…). Read the entries with get_glossary_entries.
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'list_glossary_entries')]
    public function __invoke(): array
    {
        return ['titles' => $this->glossary->getTitles()];
    }
}

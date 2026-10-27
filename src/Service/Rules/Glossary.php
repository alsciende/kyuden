<?php

declare(strict_types=1);

namespace App\Service\Rules;

/**
 * The glossary of the rules (`doc/glossary.md`) entry by entry: an entry is a section `## <title>`, whose title may name
 * several terms (`Bow, Bowed`).
 */
final readonly class Glossary
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * The titles of the entries, in the order of the glossary (alphabetical).
     *
     * @return list<string>
     */
    public function getTitles(): array
    {
        return array_keys($this->getEntries());
    }

    /**
     * The entry of a title, or of one of the terms it names, whatever its case.
     *
     * @return ?array{title: string, text: string}
     */
    public function find(string $term): ?array
    {
        $term = $this->normalize($term);
        foreach ($this->getEntries() as $title => $text) {
            $terms = [$title, ...explode(',', $title)];
            if (\in_array($term, array_map($this->normalize(...), $terms), true)) {
                return ['title' => $title, 'text' => $text];
            }
        }

        return null;
    }

    /**
     * @return array<string, string> the text of each entry, by title
     */
    private function getEntries(): array
    {
        $content = file_get_contents("{$this->projectDir}/doc/glossary.md");
        if ($content === false) {
            throw new \RuntimeException('Cannot read the glossary.');
        }

        // the introduction of the glossary, before the first entry, is dropped
        $sections = preg_split('/^## /m', $content);
        \assert(\is_array($sections));
        $entries = [];
        foreach (\array_slice($sections, 1) as $section) {
            [$title, $text] = explode("\n", $section, 2) + [1 => ''];
            $entries[trim($title)] = trim($text);
        }

        return $entries;
    }

    private function normalize(string $term): string
    {
        return mb_strtolower(trim($term));
    }
}

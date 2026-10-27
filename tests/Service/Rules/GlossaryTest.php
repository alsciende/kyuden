<?php

declare(strict_types=1);

namespace App\Tests\Service\Rules;

use App\Service\Rules\Glossary;
use App\Tests\AbstractKernelTestCase;

class GlossaryTest extends AbstractKernelTestCase
{
    public function testEveryEntryIsATitle(): void
    {
        $titles = self::service(Glossary::class)->getTitles();

        self::assertCount(145, $titles);
        self::assertSame('Ability', $titles[0]);
    }

    public function testAnEntryIsFoundByAnyOfItsTermsWhateverTheCase(): void
    {
        $glossary = self::service(Glossary::class);

        $entry = $glossary->find(' attacking PLAYER ');

        self::assertNotNull($entry);
        self::assertSame('Attacker, Attacking Character, Attacking Player', $entry['title']);
        self::assertStringNotContainsString('## ', $entry['text']);
        self::assertNull($glossary->find('Attacking'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Deck;

use App\Service\Deck\DeckConstructionValidator;
use App\Service\Deck\EmeraldDbDeckFormat;
use App\Tests\AbstractKernelTestCase;

class DeckConstructionValidatorTest extends AbstractKernelTestCase
{
    /**
     * A legal Crane deck built on EmeraldDB: Keeper of Water (water role cards, 3 more influence spent on 12
     * influence of Crab cards), a province of two elements.
     *
     * @return array<string, int> quantity by card code
     */
    public static function craneDeck(): array
    {
        $cards = json_decode((string) file_get_contents(__DIR__ . '/../../fixtures/emeralddb-crane-deck.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cards);
        /** @var array<string, int> $cards */
        $converted = self::service(EmeraldDbDeckFormat::class)->toCodes($cards);
        self::assertSame([], $converted['unknown']);

        return $converted['cards'];
    }

    /**
     * @param array<string, int> $cards
     *
     * @return list<string>
     */
    private function validate(array $cards): array
    {
        return self::service(DeckConstructionValidator::class)->validate($cards);
    }

    public function testALegalDeckBreaksNoRule(): void
    {
        self::assertSame([], $this->validate(self::craneDeck()));
    }

    public function testTheRoleAddsInfluenceAndAllowsTheCardsRestrictedToIt(): void
    {
        $deck = self::craneDeck();
        unset($deck['01-keeper-of-water']);

        $errors = $this->validate($deck);

        self::assertContains('Fight On requires a water role.', $errors);
        self::assertContains('Along the River of Gold requires a water role.', $errors);
        self::assertContains('The out-of-clan cards cost 12 influence, the deck has 9.', $errors);
    }

    public function testTheDeckHasOneStrongholdAndAtMostOneRole(): void
    {
        $deck = self::craneDeck();
        $deck['01-shizuka-toshi'] = 1;
        $deck['01-keeper-of-air'] = 1;

        $errors = $this->validate($deck);

        self::assertContains('The deck must have exactly 1 stronghold.', $errors);
        self::assertContains('The deck can have at most 1 role.', $errors);
    }

    public function testTheDecksHave40To45Cards(): void
    {
        $deck = self::craneDeck();
        $deck['01-banzai'] += 4;
        unset($deck['01-brash-samurai']);

        $errors = $this->validate($deck);

        self::assertContains('The conflict deck must have 40 to 45 cards, it has 46.', $errors);
        self::assertContains('The dynasty deck must have 40 to 45 cards, it has 39.', $errors);
    }

    public function testAtMost3CopiesByTitle(): void
    {
        $deck = self::craneDeck();
        // Daidoji Uji is already there in its second version
        $deck['18-daidoji-uji'] = 1;

        self::assertSame(['The deck can have at most 3 copies of Daidoji Uji, it has 4.'], $this->validate($deck));
    }

    public function testTheProvincesStandForEachElement(): void
    {
        $deck = self::craneDeck();
        unset($deck['01-shameful-display']);
        $deck['01-ancestral-lands'] = 1;

        self::assertSame(['The provinces must be associated with each element, one province per element.'], $this->validate($deck));
    }

    public function testASeekerRoleAllowsAnAdditionalProvinceOfItsElement(): void
    {
        $deck = self::craneDeck();
        unset($deck['01-shameful-display'], $deck['01-keeper-of-water']);
        $deck['01-ancestral-lands'] = 1;
        $deck['01-seeker-of-earth'] = 1;

        self::assertNotContains('The provinces must be associated with each element, one province per element.', $this->validate($deck));
    }

    public function testTheProvincesHaveDifferentTitles(): void
    {
        $deck = self::craneDeck();
        $deck['01-entrenched-position'] = 2;
        unset($deck['01-shameful-display']);

        self::assertSame(['The deck can have at most 1 copy of the province Entrenched Position.'], $this->validate($deck));
    }

    public function testTheDynastyCardsAreInClanOrNeutral(): void
    {
        $deck = self::craneDeck();
        unset($deck['01-brash-samurai']);
        $deck['01-bayushi-liar'] = 3;

        self::assertSame(['Bayushi Liar is not in-clan nor neutral.'], $this->validate($deck));
    }

    public function testTheOutOfClanConflictCardsComeFromASingleClan(): void
    {
        $deck = self::craneDeck();
        unset($deck['12-fight-on']);
        $deck['01-ready-for-battle'] = 3;

        self::assertSame(['The out-of-clan conflict cards must come from a single clan.'], $this->validate($deck));
    }
}

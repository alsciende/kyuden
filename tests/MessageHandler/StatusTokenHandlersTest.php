<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Exception\Rules\NoStatusTokenException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\PersonalHonorException;
use App\Exception\Rules\SameCharacterException;
use App\Exception\Rules\WrongCardTypeException;
use App\Enum\StatusToken;
use App\Message\DiscardStatusToken;
use App\Message\MoveStatusToken;
use App\Message\PlaceStatusToken;
use App\MessageHandler\DiscardStatusTokenHandler;
use App\MessageHandler\MoveStatusTokenHandler;
use App\MessageHandler\PlaceStatusTokenHandler;
use App\State\Manager\CardStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * Effects on the status tokens of characters and provinces, which neither honor nor dishonor them: accepted in any
 * place, from any player, on any player's cards.
 */
class StatusTokenHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithCharacters(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'], personalHonor: PersonalHonor::Honored)
                ->inPlay(['scout' => '01-eager-scout'])
                ->dynastyDiscardPile(['whisperer' => '01-doji-whisperer']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceDown)
                ->inPlay(['spear' => '01-master-of-the-spear'], personalHonor: PersonalHonor::Dishonored)
                ->inPlay(['hotaru' => '01-doji-hotaru'], personalHonor: PersonalHonor::Honored))
            ->persist();
    }

    private function personalHonor(Scenario $scenario, string $card): PersonalHonor
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card))->getPersonalHonor();
    }

    private function discard(Scenario $scenario, string $card): void
    {
        self::service(DiscardStatusTokenHandler::class)(new DiscardStatusToken($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($card)->getId()));
    }

    private function place(Scenario $scenario, string $card, StatusToken $token): void
    {
        self::service(PlaceStatusTokenHandler::class)(new PlaceStatusToken($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($card)->getId(), $token));
    }

    private function move(Scenario $scenario, string $from, string $to): void
    {
        self::service(MoveStatusTokenHandler::class)(new MoveStatusToken($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($from)->getId(), $scenario->card($to)->getId()));
    }

    public function testDiscardsTheHonoredTokenOfACharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->discard($scenario, 'toturi');

        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'toturi'));
    }

    public function testDiscardsTheDishonoredTokenOfAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->discard($scenario, 'spear');

        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'spear'));
    }

    public function testRejectsDiscardingTheTokenOfAnOrdinaryCharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(NoStatusTokenException::class);

        $this->discard($scenario, 'scout');
    }

    public function testRejectsDiscardingTheTokenOfACardThatIsNotACharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(WrongCardTypeException::class);

        $this->discard($scenario, 'katana');
    }

    public function testMovesATokenToAnOrdinaryCharacterOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->move($scenario, 'spear', 'scout');

        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'spear'));
        self::assertSame(PersonalHonor::Dishonored, $this->personalHonor($scenario, 'scout'));
    }

    public function testATokenMovedToACharacterWithTheOtherTokenDiscardsBoth(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->move($scenario, 'toturi', 'spear');

        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'toturi'));
        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'spear'));
    }

    public function testRejectsMovingATokenToACharacterWithATokenOfTheSameKind(): void
    {
        $scenario = $this->scenarioWithCharacters();

        try {
            $this->move($scenario, 'toturi', 'hotaru');
            self::fail('The token was moved.');
        } catch (PersonalHonorException) {
            self::assertSame(PersonalHonor::Honored, $this->personalHonor($scenario, 'toturi'));
            self::assertSame(PersonalHonor::Honored, $this->personalHonor($scenario, 'hotaru'));
        }
    }

    public function testRejectsMovingTheTokenOfAnOrdinaryCharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(NoStatusTokenException::class);

        $this->move($scenario, 'scout', 'toturi');
    }

    public function testRejectsMovingATokenToTheSameCharacter(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(SameCharacterException::class);

        $this->move($scenario, 'toturi', 'toturi');
    }

    public function testRejectsMovingATokenToACardThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(NotInPlayException::class);

        $this->move($scenario, 'toturi', 'whisperer');
    }

    public function testPlacesATokenOnAnOrdinaryCharacterOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->place($scenario, 'scout', StatusToken::Dishonored);

        self::assertSame(PersonalHonor::Dishonored, $this->personalHonor($scenario, 'scout'));
    }

    public function testATokenPlacedOnACharacterWithTheOtherTokenDiscardsBoth(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->place($scenario, 'spear', StatusToken::Honored);

        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'spear'));
    }

    public function testRejectsPlacingATokenOnACharacterWithATokenOfTheSameKind(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(PersonalHonorException::class);

        $this->place($scenario, 'toturi', StatusToken::Honored);
    }

    public function testRejectsPlacingATokenOnACharacterThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(NotInPlayException::class);

        $this->place($scenario, 'whisperer', StatusToken::Honored);
    }

    public function testRejectsPlacingATokenOnAnAttachment(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->expectException(WrongCardTypeException::class);

        $this->place($scenario, 'katana', StatusToken::Dishonored);
    }

    public function testPlacesAndDiscardsATokenOfAFacedownProvinceOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->place($scenario, 'province', StatusToken::Dishonored);
        self::assertSame(PersonalHonor::Dishonored, $this->personalHonor($scenario, 'province'));

        $this->discard($scenario, 'province');
        self::assertSame(PersonalHonor::Ordinary, $this->personalHonor($scenario, 'province'));
    }

    public function testAcceptsAnHonoredTokenOnAProvince(): void
    {
        $scenario = $this->scenarioWithCharacters();

        $this->place($scenario, 'province', StatusToken::Honored);

        self::assertSame(PersonalHonor::Honored, $this->personalHonor($scenario, 'province'));
    }

    public function testRejectsMovingTheTokenOfAProvince(): void
    {
        $scenario = $this->scenarioWithCharacters();
        $this->place($scenario, 'province', StatusToken::Dishonored);

        $this->expectException(WrongCardTypeException::class);

        $this->move($scenario, 'province', 'scout');
    }
}

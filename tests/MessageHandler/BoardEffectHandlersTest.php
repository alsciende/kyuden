<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Enum\ConflictType;
use App\Enum\Orientation;
use App\Exception\Rules\AlreadyAttachedException;
use App\Exception\Rules\AlreadyFirstPlayerException;
use App\Exception\Rules\ImperialFavorUnclaimedException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\NotInProvinceException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\SameProvinceException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\BreakProvince;
use App\Message\DiscardImperialFavor;
use App\Message\MoveAttachment;
use App\Message\SwitchProvinceCards;
use App\Message\TakeFirstPlayerToken;
use App\Message\TakeImperialFavor;
use App\MessageHandler\BreakProvinceHandler;
use App\MessageHandler\DiscardImperialFavorHandler;
use App\MessageHandler\MoveAttachmentHandler;
use App\MessageHandler\SwitchProvinceCardsHandler;
use App\MessageHandler\TakeFirstPlayerTokenHandler;
use App\MessageHandler\TakeImperialFavorHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

/**
 * Effects on the board: moving an attachment, switching the cards of two provinces, breaking a province, taking the
 * first player token, discarding and taking the Imperial Favor.
 */
class BoardEffectHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithBoard(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'])
                ->inPlay(['scout' => '01-eager-scout'])
                ->hand(['fan' => '01-ornate-fan'])
                ->dynastyDiscardPile(['discarded' => '01-doji-whisperer'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-borderlands-fortifications', dynastyAs: 'fortifications', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('01-pilgrimage', as: 'other-province', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->strongholdProvince('01-shameful-display', as: 'stronghold-province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-the-art-of-peace', as: 'jane-province', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true))
            ->firstPlayer('john')
            ->persist();
    }

    private function game(Scenario $scenario): Game
    {
        return $scenario->game();
    }

    private function sender(Scenario $scenario): string
    {
        return $scenario->player('jane')->getId();
    }

    private function moveAttachment(Scenario $scenario, string $card, string $target): void
    {
        self::service(MoveAttachmentHandler::class)(new MoveAttachment($this->game($scenario)->getId(), $this->sender($scenario), $scenario->card($card)->getId(), $scenario->card($target)->getId()));
    }

    private function switchCards(Scenario $scenario, string $card, string $other): void
    {
        self::service(SwitchProvinceCardsHandler::class)(new SwitchProvinceCards($this->game($scenario)->getId(), $this->sender($scenario), $scenario->card($card)->getId(), $scenario->card($other)->getId()));
    }

    private function breakProvince(Scenario $scenario, string $province): void
    {
        self::service(BreakProvinceHandler::class)(new BreakProvince($this->game($scenario)->getId(), $this->sender($scenario), $scenario->card($province)->getId()));
    }

    public function testMovesAnAttachmentToACharacterOfAnyPlayer(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->moveAttachment($scenario, 'katana', 'spear');

        self::assertSame($scenario->card('spear')->getStack(), $scenario->card('katana')->getStack());
        self::assertCount(1, $scenario->card('toturi')->getStack()->getCards());
        self::assertSame(["Fine Katana is moved from john's play area to jane's play area"], $this->logLines($this->game($scenario)));
    }

    public function testMovesAnAttachmentToAProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->moveAttachment($scenario, 'katana', 'province');

        self::assertSame($scenario->card('province')->getStack(), $scenario->card('katana')->getStack());
    }

    public function testRejectsACardThatIsNotAnAttachment(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(WrongCardTypeException::class);

        $this->moveAttachment($scenario, 'scout', 'spear');
    }

    public function testRejectsAnAttachmentThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotEnabledTransitionException::class);

        $this->moveAttachment($scenario, 'fan', 'spear');
    }

    public function testRejectsTheCharacterTheAttachmentIsOn(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(AlreadyAttachedException::class);

        $this->moveAttachment($scenario, 'katana', 'toturi');
    }

    public function testRejectsATargetThatIsNotInPlay(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotInPlayException::class);

        $this->moveAttachment($scenario, 'katana', 'samurai');
    }

    public function testSwitchesTwoCardsOfDifferentProvincesKeepingTheirOrientation(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->switchCards($scenario, 'fortifications', 'samurai');

        self::assertSame($scenario->card('other-province')->getStack(), $scenario->card('fortifications')->getStack());
        self::assertSame($scenario->card('province')->getStack(), $scenario->card('samurai')->getStack());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('fortifications')->getCurrentPlace());
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $scenario->card('samurai')->getCurrentPlace());
        self::assertSame([
            "Borderlands Fortifications is moved from john's province to john's province",
            "a facedown card is moved from john's province to john's province",
        ], $this->logLines($this->game($scenario), $scenario->player('jane')));
    }

    public function testRejectsTwoCardsOfTheSameProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(SameProvinceException::class);

        $this->switchCards($scenario, 'fortifications', 'fortifications');
    }

    public function testRejectsCardsOfDifferentPlayers(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotOwnedCardException::class);

        $this->switchCards($scenario, 'fortifications', 'hotaru');
    }

    public function testRejectsACardThatIsNotADynastyCardInAProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotInProvinceException::class);

        $this->switchCards($scenario, 'fortifications', 'discarded');
    }

    public function testRejectsAProvinceCard(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotInProvinceException::class);

        $this->switchCards($scenario, 'fortifications', 'other-province');
    }

    public function testBreaksAFaceupProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->breakProvince($scenario, 'province');

        self::assertSame(ProvinceCard::STATE_BROKEN, $scenario->card('province')->getCurrentPlace());
        self::assertSame(['Entrenched Position is broken'], $this->logLines($this->game($scenario)));
    }

    public function testRejectsAFacedownProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(NotEnabledTransitionException::class);

        $this->breakProvince($scenario, 'other-province');
    }

    public function testRejectsACardThatIsNotAProvince(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(WrongCardTypeException::class);

        $this->breakProvince($scenario, 'samurai');
    }

    public function testBreakingTheStrongholdProvinceEndsTheGame(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->messageBus->dispatch(new BreakProvince($this->game($scenario)->getId(), $this->sender($scenario), $scenario->card('stronghold-province')->getId()));

        self::assertSame(Game::STATE_ENDED, $this->game($scenario)->getCurrentPlace());
        self::assertSame($scenario->player('jane')->getId(), self::service(GameStateManager::class)->getState($this->game($scenario))->winnerId);
    }

    public function testAPlayerTakesTheFirstPlayerToken(): void
    {
        $scenario = $this->scenarioWithBoard();

        self::service(TakeFirstPlayerTokenHandler::class)(new TakeFirstPlayerToken($this->game($scenario)->getId(), $scenario->player('john')->getId(), $scenario->player('jane')->getId()));

        self::assertSame($scenario->player('jane')->getId(), self::service(GameStateManager::class)->getState($this->game($scenario))->firstPlayerId);
        self::assertSame(['jane is the first player'], $this->logLines($this->game($scenario)));
    }

    public function testRejectsTheFirstPlayerTakingTheToken(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(AlreadyFirstPlayerException::class);

        self::service(TakeFirstPlayerTokenHandler::class)(new TakeFirstPlayerToken($this->game($scenario)->getId(), $this->sender($scenario), $scenario->player('john')->getId()));
    }

    public function testDiscardsTheImperialFavor(): void
    {
        $scenario = $this->scenarioWithImperialFavor('jane', ConflictType::Political);
        $handler = self::service(DiscardImperialFavorHandler::class);
        $handler->assertAvailable($this->game($scenario), $scenario->player('john'));

        $handler(new DiscardImperialFavor($this->game($scenario)->getId(), $scenario->player('john')->getId()));

        $state = self::service(GameStateManager::class)->getState($this->game($scenario));
        self::assertNull($state->imperialFavorHolderId);
        self::assertNull($state->imperialFavorSide);
        self::assertSame(['jane loses the Imperial Favor'], $this->logLines($this->game($scenario)));
    }

    public function testRejectsDiscardingTheUnclaimedImperialFavor(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->expectException(ImperialFavorUnclaimedException::class);

        self::service(DiscardImperialFavorHandler::class)->assertAvailable($this->game($scenario), $scenario->player('john'));
    }

    public function testAPlayerTakesTheUnclaimedImperialFavor(): void
    {
        $scenario = $this->scenarioWithBoard();

        $this->takeImperialFavor($scenario, 'john', ConflictType::Military);

        $state = self::service(GameStateManager::class)->getState($this->game($scenario));
        self::assertSame($scenario->player('john')->getId(), $state->imperialFavorHolderId);
        self::assertSame(ConflictType::Military, $state->imperialFavorSide);
        self::assertSame('conflict_phase_action_window', $this->game($scenario)->getCurrentPlace());
        self::assertSame(['john claims the Imperial Favor, military side up'], $this->logLines($this->game($scenario)));
    }

    public function testAPlayerTakesTheImperialFavorFromTheOtherOne(): void
    {
        $scenario = $this->scenarioWithImperialFavor('jane', ConflictType::Political);

        $this->takeImperialFavor($scenario, 'john', ConflictType::Military);

        $state = self::service(GameStateManager::class)->getState($this->game($scenario));
        self::assertSame($scenario->player('john')->getId(), $state->imperialFavorHolderId);
        self::assertSame(ConflictType::Military, $state->imperialFavorSide);
    }

    public function testTheHolderClaimsTheImperialFavorAgainOnTheOtherSide(): void
    {
        $scenario = $this->scenarioWithImperialFavor('jane', ConflictType::Political);

        $this->takeImperialFavor($scenario, 'jane', ConflictType::Military);

        $state = self::service(GameStateManager::class)->getState($this->game($scenario));
        self::assertSame($scenario->player('jane')->getId(), $state->imperialFavorHolderId);
        self::assertSame(ConflictType::Military, $state->imperialFavorSide);
        self::assertSame(['jane claims the Imperial Favor, military side up'], $this->logLines($this->game($scenario)));
    }

    private function scenarioWithImperialFavor(string $holder, ConflictType $side): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->withGameState(function (GameState $state, Scenario $scenario) use ($holder, $side): void {
                $state->imperialFavorHolderId = $scenario->player($holder)->getId();
                $state->imperialFavorSide = $side;
            })
            ->persist();
    }

    private function takeImperialFavor(Scenario $scenario, string $target, ConflictType $side): void
    {
        self::service(TakeImperialFavorHandler::class)(new TakeImperialFavor($this->game($scenario)->getId(), $this->sender($scenario), $scenario->player($target)->getId(), $side));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Rules\CannotFillProvinceException;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PutAttachmentIntoPlay;
use App\Message\PutCharacterIntoPlay;
use App\Message\PutHoldingIntoProvince;
use App\Message\ShuffleIntoDeck;
use App\MessageHandler\PutAttachmentIntoPlayHandler;
use App\MessageHandler\PutCharacterIntoPlayHandler;
use App\MessageHandler\PutHoldingIntoProvinceHandler;
use App\MessageHandler\ShuffleIntoDeckHandler;
use App\Service\ControlService;
use App\Service\ProvinceRefiller;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

/**
 * Cards put into play by an effect, without being played: characters, attachments, and holdings into a province.
 */
class PutIntoPlayHandlersTest extends AbstractKernelTestCase
{
    private function builder(string $place = 'draw_phase_action_window'): GameBuilder
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(4)
                ->hand(['ambusher' => '01-hiruma-ambusher', 'katana' => '01-fine-katana'])
                ->conflictDeck(['fan' => '01-ornate-fan'])
                ->conflictDiscardPile(['adept' => '01-adept-of-shadows'])
                ->removedFromGame(['blade' => '01-honored-blade'])
                ->dynastyDeck(['whisperer' => '01-doji-whisperer', 'scout' => '01-eager-scout'])
                ->dynastyDiscardPile(['keeper' => '01-keeper-initiate', 'academy' => '01-artisan-academy'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-ikoma-eiji', dynastyAs: 'eiji', dynastyFaceup: true)
                ->province('01-pilgrimage', as: 'hidden', dynasty: '01-brash-samurai', dynastyAs: 'samurai')
                ->strongholdProvince('01-shameful-display', as: 'stronghold-province')
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-the-art-of-peace', as: 'jane-province', orientation: Orientation::FaceUp));
    }

    private function putIntoPlay(Scenario $scenario, string $card, bool $intoConflict = false, ?string $controller = null): void
    {
        // the opponent sends it: any player can
        self::service(PutCharacterIntoPlayHandler::class)(new PutCharacterIntoPlay(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
            $intoConflict,
            $controller === null ? null : $scenario->player($controller)->getId(),
        ));
    }

    private function putAttachmentIntoPlay(Scenario $scenario, string $card, string $target, ?string $controller = null): void
    {
        // the opponent sends it: any player can
        self::service(PutAttachmentIntoPlayHandler::class)(new PutAttachmentIntoPlay(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($card)->getId(),
            $scenario->card($target)->getId(),
            $controller === null ? null : $scenario->player($controller)->getId(),
        ));
    }

    private function putIntoProvince(Scenario $scenario, string $holding, string $province): void
    {
        self::service(PutHoldingIntoProvinceHandler::class)(new PutHoldingIntoProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($holding)->getId(),
            $scenario->card($province)->getId(),
        ));
    }

    /**
     * @return list<string> the names of the cards of a stack, from the bottom to the top
     */
    private function names(PhysicalCard $card): array
    {
        return array_map(fn (PhysicalCard $other): string => $other->getLogicalCard()->getName(), $card->getStack()->getOrderedCards());
    }

    public function testACharacterOfAProvinceIsPutIntoPlayAtHome(): void
    {
        $scenario = $this->builder()->persist();

        $this->putIntoPlay($scenario, 'eiji');

        $eiji = $scenario->card('eiji');
        self::assertSame(DynastyCard::STATE_IN_PLAY, $eiji->getCurrentPlace());
        self::assertSame(Orientation::FaceUp, $eiji->getOrientation());
        $this->assertRefillScheduled($scenario->card('province'));
        // nothing is paid, no opportunity is used
        self::assertSame(4, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getFate());
        self::assertFalse(self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->hasActed());
        self::assertSame(["Ikoma Eiji is put into play from john's province"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACharacterOfTheDynastyDiscardPileIsPutIntoTheConflictOnTheSideOfItsOwner(): void
    {
        $scenario = $this->builder('conflict_action_window')->conflict('jane', 'province', attackers: ['spear'])->persist();

        $this->putIntoPlay($scenario, 'keeper', intoConflict: true);

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('keeper')->getCurrentPlace());
        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('keeper')->getId()], $conflict->defenderIds);
        self::assertSame([$scenario->card('spear')->getId()], $conflict->attackerIds);
        self::assertSame([
            "Keeper Initiate is put into play from john's dynasty discard pile",
            'Keeper Initiate joins the conflict',
        ], $this->logLines($scenario->game()));
    }

    public function testACharacterIsPutIntoTheConflictUnderTheControlOfAnotherPlayer(): void
    {
        $scenario = $this->builder('conflict_action_window')->conflict('jane', 'province', attackers: ['spear'])->persist();

        $this->putIntoPlay($scenario, 'keeper', intoConflict: true, controller: 'jane');

        $keeper = $scenario->card('keeper');
        self::assertSame(DynastyCard::STATE_IN_PLAY, $keeper->getCurrentPlace());
        self::assertSame($scenario->player('jane'), $keeper->getStack()->getPlayer());
        self::assertSame($scenario->player('jane'), self::service(ControlService::class)->getController($keeper));
        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('spear')->getId(), $keeper->getId()], $conflict->attackerIds);
        self::assertSame([], $conflict->defenderIds);
        self::assertSame([
            "Keeper Initiate is put into play from john's dynasty discard pile",
            'jane takes control of Keeper Initiate',
            'Keeper Initiate joins the conflict',
        ], $this->logLines($scenario->game()));
    }

    public function testACharacterPutIntoPlayUnderTheControlOfItsOwnerDoesNotChangeControl(): void
    {
        $scenario = $this->builder()->persist();

        $this->putIntoPlay($scenario, 'adept', controller: 'john');

        self::assertNull(self::service(CardStateManager::class)->getState($scenario->card('adept'))->getControllerId());
        self::assertSame(["Adept of Shadows is put into play from john's conflict discard pile"], $this->logLines($scenario->game()));
    }

    public function testACharacterOfTheHandIsPutIntoPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->putIntoPlay($scenario, 'ambusher');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('ambusher')->getCurrentPlace());
    }

    public function testACharacterOfTheConflictDiscardPileIsPutIntoPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->putIntoPlay($scenario, 'adept');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('adept')->getCurrentPlace());
    }

    public function testACharacterOfTheDynastyDeckIsPutIntoPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->putIntoPlay($scenario, 'scout');

        self::assertSame(DynastyCard::STATE_IN_PLAY, $scenario->card('scout')->getCurrentPlace());
        self::assertSame(["Eager Scout is put into play from john's dynasty deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACharacterOfTheConflictDeckIsPutIntoPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictDeck(['ambusher' => '01-hiruma-ambusher']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();

        $this->putIntoPlay($scenario, 'ambusher');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('ambusher')->getCurrentPlace());
    }

    public function testRejectsACardThatIsNotACharacter(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(WrongCardTypeException::class);

        $this->putIntoPlay($scenario, 'academy');
    }

    public function testRejectsAFacedownCharacterOfAProvince(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotEnabledTransitionException::class);

        $this->putIntoPlay($scenario, 'samurai');
    }

    public function testRejectsACharacterAlreadyInPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotEnabledTransitionException::class);

        $this->putIntoPlay($scenario, 'toturi');
    }

    public function testRejectsTheConflictWithoutAConflict(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NoConflictException::class);

        $this->putIntoPlay($scenario, 'keeper', intoConflict: true);
    }

    public function testAnAttachmentOfTheHandIsPutIntoPlayOnACharacterOfAnotherPlayer(): void
    {
        $scenario = $this->builder()->persist();

        $this->putAttachmentIntoPlay($scenario, 'katana', 'spear');

        $katana = $scenario->card('katana');
        self::assertSame(ConflictCard::STATE_IN_PLAY, $katana->getCurrentPlace());
        self::assertSame(Orientation::FaceUp, $katana->getOrientation());
        self::assertSame(['Master of the Spear', 'Fine Katana'], $this->names($katana));
        // under the control of its owner, whose card it remains
        self::assertSame($scenario->player('john'), $katana->getPlayer());
        self::assertNull(self::service(CardStateManager::class)->getState($katana)->getControllerId());
        // nothing is paid, no opportunity is used
        self::assertSame(4, self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getFate());
        self::assertFalse(self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->hasActed());
        self::assertSame(["Fine Katana is put into play from john's hand"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testAnAttachmentOfTheConflictDeckIsPutIntoPlayOnAProvince(): void
    {
        $scenario = $this->builder()->persist();

        $this->putAttachmentIntoPlay($scenario, 'fan', 'province');

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('fan')->getCurrentPlace());
        self::assertSame(['Entrenched Position', 'Ikoma Eiji', 'Ornate Fan'], $this->names($scenario->card('province')));
        self::assertSame(["Ornate Fan is put into play from john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testAnAttachmentRemovedFromTheGameIsPutIntoPlayUnderTheControlOfAnotherPlayer(): void
    {
        $scenario = $this->builder()->persist();

        $this->putAttachmentIntoPlay($scenario, 'blade', 'toturi', controller: 'jane');

        $blade = $scenario->card('blade');
        self::assertSame(ConflictCard::STATE_IN_PLAY, $blade->getCurrentPlace());
        self::assertSame(['Akodo Toturi', 'Honored Blade'], $this->names($blade));
        self::assertSame($scenario->player('jane'), self::service(ControlService::class)->getController($blade));
        self::assertSame([
            "Honored Blade is put into play from john's cards removed from the game",
            'jane takes control of Honored Blade',
        ], $this->logLines($scenario->game()));
    }

    public function testAnAttachmentOfTheConflictDiscardPileIsPutIntoPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->conflictDiscardPile(['katana' => '01-fine-katana'])
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();

        $this->putAttachmentIntoPlay($scenario, 'katana', 'toturi');

        self::assertSame(['Akodo Toturi', 'Fine Katana'], $this->names($scenario->card('katana')));
    }

    public function testRejectsACardThatIsNotAnAttachment(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(WrongCardTypeException::class);

        $this->putAttachmentIntoPlay($scenario, 'ambusher', 'toturi');
    }

    public function testRejectsAnAttachmentOnACardThatIsNotInPlay(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotInPlayException::class);

        $this->putAttachmentIntoPlay($scenario, 'katana', 'adept');
    }

    public function testRejectsAnAttachmentAlreadyInPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'])
                ->inPlay(['spear' => '01-master-of-the-spear']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();

        $this->expectException(NotEnabledTransitionException::class);

        $this->putAttachmentIntoPlay($scenario, 'katana', 'spear');
    }

    public function testAHoldingIsPutIntoAProvinceBesideItsCardAndKeepsItFromBeingRefilled(): void
    {
        $scenario = $this->builder()->persist();

        // Rebuild: the card of the province is shuffled into the deck, then the holding is put into the province,
        // which is still empty once the effect is resolved
        self::service(ShuffleIntoDeckHandler::class)(new ShuffleIntoDeck($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('eiji')->getId()));
        $this->putIntoProvince($scenario, 'academy', 'province');
        self::service(ProvinceRefiller::class)->refillScheduled($scenario->game());

        $academy = $scenario->card('academy');
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $academy->getCurrentPlace());
        self::assertSame(['Entrenched Position', 'Artisan Academy'], $this->names($scenario->card('province')));
        self::assertSame(3, $scenario->player('john')->getUniqueStack(StackType::DynastyDrawDeck)->getCards()->count());
        self::assertContains("Artisan Academy is put from john's dynasty discard pile into john's province", $this->logLines($scenario->game()));
    }

    public function testAHoldingOfTheDynastyDeckIsPutIntoAProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->dynastyDeck(['ground' => '01-favorable-ground', '01-eager-scout'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-ikoma-eiji', dynastyFaceup: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();

        $this->putIntoProvince($scenario, 'ground', 'province');

        self::assertSame(DynastyCard::STATE_PROVINCE_FACEUP, $scenario->card('ground')->getCurrentPlace());
        self::assertSame(['Entrenched Position', 'Ikoma Eiji', 'Favorable Ground'], $this->names($scenario->card('province')));
        self::assertSame(["Favorable Ground is put from john's dynasty deck into john's province"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testRejectsAHoldingIntoTheStrongholdProvince(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(CannotFillProvinceException::class);

        $this->putIntoProvince($scenario, 'academy', 'stronghold-province');
    }

    public function testRejectsAHoldingIntoAProvinceOfAnotherPlayer(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(NotOwnedCardException::class);

        $this->putIntoProvince($scenario, 'academy', 'jane-province');
    }

    public function testRejectsACardThatIsNotAHolding(): void
    {
        $scenario = $this->builder()->persist();

        $this->expectException(WrongCardTypeException::class);

        $this->putIntoProvince($scenario, 'keeper', 'province');
    }

    public function testRejectsAHoldingThatIsNotInTheDiscardPile(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', dynasty: '01-artisan-academy', dynastyAs: 'academy', dynastyFaceup: true)
                ->province('01-pilgrimage', as: 'other'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p)
            ->persist();

        $this->expectException(NotEnabledTransitionException::class);

        $this->putIntoProvince($scenario, 'academy', 'other');
    }
}

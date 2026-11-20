<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\DynastyCardTurnedFaceupEvent;
use App\Message\RevealDynastyCard;
use App\MessageHandler\RevealDynastyCardHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

/**
 * The cards reacting to a dynasty card turned face up in its province: Mountaintop Statuary, and Lost Papers, a dynasty
 * event, during the dynasty phase only, when it can be played from its province.
 */
class DynastyCardTurnedFaceupReactionsTest extends AbstractKernelTestCase
{
    public function testEveryCardReactingToADynastyCardTurnedFaceupSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(DynastyCardTurnedFaceupEvent::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing(['05-mountaintop-statuary', '31-lost-papers'], $codes);
    }

    public function testTheHoldingTurnedFaceup(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '05-mountaintop-statuary', dynastyAs: 'statuary'))
            ->withPlayer('jane')
            ->persist();

        self::service(RevealDynastyCardHandler::class)(new RevealDynastyCard($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('statuary')->getId()));

        $windows = self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getReactionWindows();
        self::assertCount(1, $windows);
        self::assertSame([$scenario->card('statuary')->getId()], $windows[0]->cardIds);
    }

    /**
     * @return list<string> the ids of the cards offered to john
     */
    private function revealLostPapers(string $place): array
    {
        $scenario = $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '31-lost-papers', dynastyAs: 'papers'))
            ->withPlayer('jane')
            ->persist();

        self::service(RevealDynastyCardHandler::class)(new RevealDynastyCard($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('papers')->getId()));

        return array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player('john'))->getReactionWindows(),
        ));
    }

    public function testLostPapersRevealedDuringTheDynastyPhase(): void
    {
        self::assertCount(1, $this->revealLostPapers('reveal_facedown_dynasty_cards'));
    }

    public function testLostPapersIsNotPlayedFromItsProvinceOutsideTheDynastyPhase(): void
    {
        self::assertSame([], $this->revealLostPapers('conflict_action_window'));
    }
}

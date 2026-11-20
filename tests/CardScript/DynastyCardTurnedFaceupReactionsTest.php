<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Event\DynastyCardTurnedFaceupEvent;
use App\Message\RevealDynastyCard;
use App\MessageHandler\RevealDynastyCardHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

/**
 * The cards reacting to a dynasty card turned face up in its province (Mountaintop Statuary); Lost Papers, a dynasty
 * event, is not offered: the engine plays the events of the conflict deck only.
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

        self::assertSame(['05-mountaintop-statuary'], $codes);
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
}

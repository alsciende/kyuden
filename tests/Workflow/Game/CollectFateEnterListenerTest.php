<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use Symfony\Component\Workflow\Registry;

class CollectFateEnterListenerTest extends AbstractKernelTestCase
{
    public function testTheFirstPlayerCollectsFateFirst(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->firstPlayer('jane')
            ->activePlayer('john')
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'dynasty_cards_revealed');

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('jane')));
    }
}

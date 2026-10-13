<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class BowAndReadyGuardListenerTest extends AbstractKernelTestCase
{
    public function testOnlyAReadyCardCanBeBowedAndOnlyABowedCardCanBeReadied(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->build();
        $registry = self::service(Registry::class);

        foreach (['stronghold', 'toturi', 'katana'] as $alias) {
            $card = $scenario->card($alias);
            $workflow = $registry->get($card);

            self::assertTrue($workflow->can($card, 'bow'), $alias);
            self::assertFalse($workflow->can($card, 'ready'), $alias);

            $workflow->apply($card, 'bow');

            self::assertFalse($workflow->can($card, 'bow'), $alias);
            self::assertTrue($workflow->can($card, 'ready'), $alias);
        }
    }
}

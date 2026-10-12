<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class DynastyCardsRevealedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeaveReveal(bool $johnFaceup, bool $janeFaceup): bool
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-akodo-toturi', faceup: $johnFaceup))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-doji-hotaru', faceup: $janeFaceup))
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'dynasty_cards_revealed');
    }

    public function testBlockedWhileAPlayerHasAFacedownDynastyCard(): void
    {
        self::assertFalse($this->canLeaveReveal(johnFaceup: true, janeFaceup: false));
    }

    public function testAllowedOnceAllDynastyCardsAreFaceup(): void
    {
        self::assertTrue($this->canLeaveReveal(johnFaceup: true, janeFaceup: true));
    }
}

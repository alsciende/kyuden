<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Orientation;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class AttackedProvinceRevealedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeaveReveal(Orientation $orientation): bool
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_attacked_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: $orientation))
            ->conflict('john', 'province', ['toturi'])
            ->persist();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'attacked_province_revealed');
    }

    public function testBlockedWhileTheAttackedProvinceIsFacedown(): void
    {
        self::assertFalse($this->canLeaveReveal(Orientation::FaceDown));
    }

    public function testAllowedOnceTheAttackedProvinceIsFaceup(): void
    {
        self::assertTrue($this->canLeaveReveal(Orientation::FaceUp));
    }
}

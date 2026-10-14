<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\PlayAttachment;
use App\MessageHandler\PlayAttachmentHandler;
use App\Service\AbilityService;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class AbilityServiceTest extends AbstractKernelTestCase
{
    /**
     * john attaches two of his attachments to a character of jane: Sturdy Tetsubo grants its ability to the attached
     * character, Fine Katana has none.
     */
    private function scenarioWithAttachmentsOnAnOpponentsCharacter(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(5)
                ->hand(['tetsubo' => '32-sturdy-tetsubo', 'katana' => '01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john')
            ->persist();
        foreach (['tetsubo', 'katana'] as $attachment) {
            self::service(PlayAttachmentHandler::class)(new PlayAttachment(
                $scenario->game()->getId(),
                $scenario->player('john')->getId(),
                $scenario->card($attachment)->getId(),
                $scenario->card('spear')->getId(),
                0,
            ));
        }

        return $scenario;
    }

    public function testAnAbilityGrantedToTheAttachedCharacterIsTriggeredByItsController(): void
    {
        $scenario = $this->scenarioWithAttachmentsOnAnOpponentsCharacter();

        self::service(AbilityService::class)->trigger($scenario->card('tetsubo'), $scenario->player('jane'));

        self::assertSame(ConflictCard::STATE_IN_PLAY, $scenario->card('tetsubo')->getCurrentPlace());
    }

    public function testAnAbilityGrantedToTheAttachedCharacterIsNotTriggeredByTheOwnerOfTheAttachment(): void
    {
        $scenario = $this->scenarioWithAttachmentsOnAnOpponentsCharacter();

        $this->expectException(NotOwnedCardException::class);

        self::service(AbilityService::class)->trigger($scenario->card('tetsubo'), $scenario->player('john'));
    }

    public function testTheOtherAbilitiesOfAnAttachmentAreTriggeredByItsOwner(): void
    {
        $scenario = $this->scenarioWithAttachmentsOnAnOpponentsCharacter();

        self::service(AbilityService::class)->trigger($scenario->card('katana'), $scenario->player('john'));

        $this->expectException(NotOwnedCardException::class);

        self::service(AbilityService::class)->trigger($scenario->card('katana'), $scenario->player('jane'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\ConflictType;
use App\Exception\Rules\NotInPlayException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\ResetCharacterSkill;
use App\Message\SetCharacterSkill;
use App\MessageHandler\ResetCharacterSkillHandler;
use App\MessageHandler\SetCharacterSkillHandler;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

/**
 * The skills of a character set by the players: accepted in any place, from any player, on any player's characters.
 */
class CharacterSkillHandlersTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi', 'katana' => '01-fine-katana'])
                ->dynastyDiscardPile(['hotaru' => '01-doji-hotaru']))
            ->persist();
    }

    private function cardState(Scenario $scenario, string $card): CardState
    {
        return self::service(CardStateManager::class)->getState($scenario->card($card));
    }

    private function setSkill(Scenario $scenario, string $card, ConflictType $type, int $skill): void
    {
        self::service(SetCharacterSkillHandler::class)(new SetCharacterSkill($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card($card)->getId(), $type, $skill));
    }

    public function testAPlayerSetsASkillOfAnOpponentsCharacter(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->setSkill($scenario, 'toturi', ConflictType::Military, 5);

        self::assertSame([5, null], [$this->cardState($scenario, 'toturi')->getMilitarySkill(), $this->cardState($scenario, 'toturi')->getPoliticalSkill()]);
    }

    public function testASkillSetAgainReplacesTheFormerOne(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->setSkill($scenario, 'toturi', ConflictType::Political, 5);
        $this->setSkill($scenario, 'toturi', ConflictType::Political, 0);

        self::assertSame(0, $this->cardState($scenario, 'toturi')->getPoliticalSkill());
    }

    public function testResettingClearsBothSkills(): void
    {
        $scenario = $this->scenarioWithCards();
        $this->setSkill($scenario, 'toturi', ConflictType::Military, 5);
        $this->setSkill($scenario, 'toturi', ConflictType::Political, 1);

        self::service(ResetCharacterSkillHandler::class)(new ResetCharacterSkill($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->card('toturi')->getId()));

        self::assertSame([null, null], [$this->cardState($scenario, 'toturi')->getMilitarySkill(), $this->cardState($scenario, 'toturi')->getPoliticalSkill()]);
    }

    public function testTheSkillsAreClearedWhenTheCharacterLeavesPlay(): void
    {
        $scenario = $this->scenarioWithCards();
        $this->setSkill($scenario, 'toturi', ConflictType::Military, 5);

        self::service(Registry::class)->get($scenario->card('toturi'))->apply($scenario->card('toturi'), 'discard_card');

        self::assertNull($this->cardState($scenario, 'toturi')->getMilitarySkill());
    }

    public function testOnlyACharacterHasSkills(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(WrongCardTypeException::class);

        $this->setSkill($scenario, 'katana', ConflictType::Military, 2);
    }

    public function testTheCharacterMustBeInPlay(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(NotInPlayException::class);

        $this->setSkill($scenario, 'hotaru', ConflictType::Military, 2);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Enum\DuelType;
use App\Message\ActionInterface;
use App\Message\BidHonor;
use App\Message\CollectFate;
use App\Message\ConcedeGame;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareDefenders;
use App\Message\DeclareDuelStatistic;
use App\Message\DeclareGloryCount;
use App\Message\DeclareProvinceStrength;
use App\Message\GainFate;
use App\Message\GainHonor;
use App\Message\InitiateDuel;
use App\Message\LoseFate;
use App\Message\LoseHonor;
use App\Message\PlayActionEvent;
use App\Message\PlayAttachment;
use App\Message\PlayCharacterFromHand;
use App\Message\PlayCharacterFromProvince;
use App\Message\PlayInterruptEvent;
use App\Message\PlayReactionEvent;
use App\Message\PlaceFate;
use App\Message\RemoveFate;
use App\Message\SetHonorBid;
use App\Message\TransferHonor;
use App\Tests\AbstractKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The values of the messages are checked by the Validator (validation middleware of the bus), before any handling.
 */
class MessageConstraintsTest extends AbstractKernelTestCase
{
    /**
     * @return iterable<string, array{ActionInterface, list<string>}>
     */
    public static function messages(): iterable
    {
        yield 'bid below one' => [new BidHonor('g', 'p', 0), ['bid']];
        yield 'bid above five' => [new BidHonor('g', 'p', 6), ['bid']];
        yield 'bid within range' => [new BidHonor('g', 'p', 5), []];
        yield 'concession reason too long' => [new ConcedeGame('g', 'p', str_repeat('a', ConcedeGame::MAX_REASON_LENGTH + 1)), ['reason']];
        yield 'concession without reason' => [new ConcedeGame('g', 'p'), []];
        yield 'negative fate collected' => [new CollectFate('g', 'p', -1), ['amount']];
        yield 'negative skill' => [new DeclareConflictSkill('g', 'p', -1), ['skill']];
        yield 'negative dueling statistic' => [new DeclareDuelStatistic('g', 'p', -1), ['statistic']];
        yield 'negative glory' => [new DeclareGloryCount('g', 'p', -1), ['glory']];
        yield 'negative province strength' => [new DeclareProvinceStrength('g', 'p', 'c', -1), ['strength']];
        yield 'negative fate gain' => [new GainFate('g', 'p', 't', -1), ['fateGain']];
        yield 'negative fate loss' => [new LoseFate('g', 'p', 't', -1), ['fateLoss']];
        yield 'negative honor gain' => [new GainHonor('g', 'p', 't', -1), ['honorGain']];
        yield 'negative honor loss' => [new LoseHonor('g', 'p', 't', -1), ['honorLoss']];
        yield 'no honor loss' => [new LoseHonor('g', 'p', 't', 0), []];
        yield 'negative honor transfer' => [new TransferHonor('g', 'p', 'a', 'b', -1), ['amount']];
        yield 'negative honor bid set' => [new SetHonorBid('g', 'p', 't', -1), ['bid']];
        yield 'no fate placed' => [new PlaceFate('g', 'p', 'c', 0), ['amount']];
        yield 'negative fate removed' => [new RemoveFate('g', 'p', 'c', -1), ['amount']];
        yield 'negative action event cost' => [new PlayActionEvent('g', 'p', 'c', -1), ['cardCost']];
        yield 'negative interrupt event cost' => [new PlayInterruptEvent('g', 'p', 'c', -1), ['cardCost']];
        yield 'negative reaction event cost' => [new PlayReactionEvent('g', 'p', 'c', -1), ['cardCost']];
        yield 'negative attachment cost' => [new PlayAttachment('g', 'p', 'c', 't', -1), ['cardCost']];
        yield 'negative costs from hand' => [new PlayCharacterFromHand('g', 'p', 'c', -1, -1, -1, false), ['cardCost', 'fateCost', 'fate']];
        yield 'negative costs from province' => [new PlayCharacterFromProvince('g', 'p', 'c', -1, -1, -1), ['cardCost', 'fateCost', 'fate']];
        yield 'no defender declared' => [new DeclareDefenders('g', 'p', []), ['defenderIds']];
        yield 'a defender declared' => [new DeclareDefenders('g', 'p', ['c']), []];
        yield 'duel without characters' => [new InitiateDuel('g', 'p', DuelType::Military, [], []), ['challengerIds', 'challengedIds']];
    }

    /**
     * @param list<string> $invalidProperties
     */
    #[DataProvider('messages')]
    public function testTheValuesOfAMessageAreValidated(ActionInterface $message, array $invalidProperties): void
    {
        $violations = self::service(ValidatorInterface::class)->validate($message);

        self::assertSame($invalidProperties, array_map(
            fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        ));
    }
}

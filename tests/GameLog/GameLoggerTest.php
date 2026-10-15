<?php

declare(strict_types=1);

namespace App\Tests\GameLog;

use App\Entity\GameLogEntry;
use App\Enum\Element;
use App\Enum\Orientation;
use App\GameLog\GameLogger;
use App\GameLog\LogParam;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;

class GameLoggerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['katana' => '01-fine-katana'])
                ->conflictDeck(['banzai' => '01-banzai'])
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'facedown')
                ->inPlay(['spear' => '01-master-of-the-spear']))
            ->withPlayer('jane')
            ->build();
    }

    /**
     * @return array{john: list<string>, jane: list<string>, spectator: list<string>}
     */
    private function linesByReader(Scenario $scenario): array
    {
        return [
            'john' => $this->logLines($scenario->game(), $scenario->player('john')),
            'jane' => $this->logLines($scenario->game(), $scenario->player('jane')),
            'spectator' => $this->logLines($scenario->game()),
        ];
    }

    public function testTheParamsReplaceThePlaceholdersOfTheTemplate(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(GameLogger::class)->log($scenario->game(), '{player} claims {ring} with {count} {flag}', [
            'player' => $scenario->player('john'),
            'ring' => Element::Air,
            'count' => 2,
            'flag' => true,
            'unused' => 'not stored',
        ]);

        self::assertSame(['john claims the air ring with 2 yes'], $this->logLines($scenario->game()));
    }

    public function testAPlaceholderWithoutParamIsAnError(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->expectException(\LogicException::class);

        self::service(GameLogger::class)->log($scenario->game(), '{player} passes');
    }

    public function testACardIsKnownToThePlayersWhoSeeItWhereItIs(): void
    {
        $scenario = $this->scenarioWithCards();
        $logger = self::service(GameLogger::class);

        foreach (['katana', 'banzai', 'province', 'facedown', 'spear'] as $alias) {
            $logger->log($scenario->game(), '{card}', [
                'card' => $scenario->card($alias),
            ]);
        }

        self::assertSame([
            'john' => ['Fine Katana', 'a hidden card', 'Entrenched Position', 'a facedown card', 'Master of the Spear'],
            'jane' => ['a hidden card', 'a hidden card', 'a facedown card', 'a facedown card', 'Master of the Spear'],
            'spectator' => ['a hidden card', 'a hidden card', 'a facedown card', 'a facedown card', 'Master of the Spear'],
        ], $this->linesByReader($scenario));
    }

    public function testASecretValueIsKnownToTheGivenPlayersOnly(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(GameLogger::class)->log($scenario->game(), '{player} bids {bid}', [
            'player' => $scenario->player('john'),
            'bid' => LogParam::value(3, [$scenario->player('john')->getId()]),
        ]);

        self::assertSame([
            'john' => ['john bids 3'],
            'jane' => ['john bids ?'],
            'spectator' => ['john bids ?'],
        ], $this->linesByReader($scenario));
    }

    public function testAListShowsEachOfItsItems(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(GameLogger::class)->log($scenario->game(), '{cards} and {none}', [
            'cards' => [$scenario->card('spear'), $scenario->card('katana')],
            'none' => [],
        ]);

        self::assertSame([
            'john' => ['Master of the Spear, Fine Katana and nothing'],
            'jane' => ['Master of the Spear, a hidden card and nothing'],
            'spectator' => ['Master of the Spear, a hidden card and nothing'],
        ], $this->linesByReader($scenario));
    }

    public function testAStackIsShownWithItsOwner(): void
    {
        $scenario = $this->scenarioWithCards();

        self::service(GameLogger::class)->log($scenario->game(), '{stack}', [
            'stack' => $scenario->card('katana')->getStack(),
        ]);

        self::assertSame(["john's hand"], $this->logLines($scenario->game()));
    }

    public function testTheEntryBelongsToTheVersionTheMessageLeadsTo(): void
    {
        $scenario = $this->scenarioWithCards();
        $scenario->game()->incrementVersion();

        self::service(GameLogger::class)->log($scenario->game(), 'Something happens');

        $entries = self::service(EntityManagerInterface::class)->getUnitOfWork()->getScheduledEntityInsertions();
        $entry = array_values(array_filter($entries, fn (object $entity): bool => $entity instanceof GameLogEntry))[0] ?? null;
        self::assertInstanceOf(GameLogEntry::class, $entry);
        self::assertSame(2, $entry->getVersion());
    }

    public function testAFaceUpProvinceIsPublic(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->withPlayer('jane')
            ->build();

        self::service(GameLogger::class)->log($scenario->game(), '{card}', [
            'card' => $scenario->card('province'),
        ]);

        self::assertSame(['Entrenched Position'], $this->logLines($scenario->game()));
    }
}

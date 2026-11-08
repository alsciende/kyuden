<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\Game;
use App\Model\ReplayIdTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

class ReplayIdTranslatorTest extends TestCase
{
    private const string SEED = '0123456789abcdef0123456789abcdef';

    public function testAnIdOfTheOriginalBecomesTheIdTheReplayGenerates(): void
    {
        $original = new Game('3f0c1c56-41a4-4b1e-9c3e-7a1d2f6e8b90', self::SEED);
        $replay = new Game('b7d2e4a1-0c5f-4e8a-a3b6-1f9e7c2d4a58', self::SEED);
        $translator = new ReplayIdTranslator($original->getId(), $replay->getId());

        for ($i = 0; $i < 10; ++$i) {
            self::assertSame($replay->generateId(), $translator->translateId($original->generateId()));
        }
    }

    public function testTheGameBecomesTheReplay(): void
    {
        $translator = new ReplayIdTranslator('original', 'replay');

        self::assertSame('replay', $translator->translateId('original'));
    }

    public function testATranslatedIdIsStillAUuidV4(): void
    {
        $translator = new ReplayIdTranslator('original', 'replay');

        self::assertInstanceOf(UuidV4::class, Uuid::fromString($translator->translateId(Uuid::v4()->toString())));
    }

    public function testWhatIsNotAnIdIsKept(): void
    {
        $translator = new ReplayIdTranslator('original', 'replay');

        self::assertSame('01-kakita-kaezin', $translator->translateId('01-kakita-kaezin'));
        self::assertSame('Bayushi Kachiko', $translator->translateId('Bayushi Kachiko'));
    }

    public function testThePayloadKeepsTheUserAndTheDeckOfAPlayerJoining(): void
    {
        $translator = new ReplayIdTranslator('original', 'replay');
        $player = Uuid::v4()->toString();
        $user = Uuid::v4()->toString();
        $deck = Uuid::v4()->toString();

        $payload = json_decode($translator->translatePayload((string) json_encode([
            'game_id' => 'original',
            'player_id' => $player,
            'user_id' => $user,
            'deck_id' => $deck,
        ])), true);

        self::assertSame([
            'game_id' => 'replay',
            'player_id' => $translator->translateId($player),
            'user_id' => $user,
            'deck_id' => $deck,
        ], $payload);
        self::assertNotSame($player, $translator->translateId($player));
    }

    public function testListsObjectsAndKeysAreTranslated(): void
    {
        $translator = new ReplayIdTranslator('original', 'replay');
        $card = Uuid::v4()->toString();
        $province = Uuid::v4()->toString();

        $payload = $translator->translatePayload((string) json_encode([
            'card_ids' => [$card],
            'province_ids' => [$card => $province],
            'empty' => new \stdClass(),
            'amount' => 2,
            'face_up' => true,
        ]));

        self::assertSame((string) json_encode([
            'card_ids' => [$translator->translateId($card)],
            'province_ids' => [$translator->translateId($card) => $translator->translateId($province)],
            'empty' => new \stdClass(),
            'amount' => 2,
            'face_up' => true,
        ]), $payload);
    }
}

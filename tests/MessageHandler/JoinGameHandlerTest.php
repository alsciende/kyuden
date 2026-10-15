<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataFixtures\AppFixtures;
use App\Exception\Data\UserNotFoundException;
use App\Message\JoinGame;
use App\MessageHandler\JoinGameHandler;
use App\Tests\AbstractKernelTestCase;

class JoinGameHandlerTest extends AbstractKernelTestCase
{
    public function testThePlayerIsTheUserWhoJoins(): void
    {
        $scenario = $this->scenario()->atPlace('join_game')->persist();

        $player = self::service(JoinGameHandler::class)(
            new JoinGame($scenario->game()->getId(), 'player', 'akodo-bot', AppFixtures::LION_STARTER_DECK),
        );

        self::assertSame('Akodo Bot', $player->getUser()->getName());
    }

    public function testTheUserMustExist(): void
    {
        $scenario = $this->scenario()->atPlace('join_game')->persist();

        $this->expectException(UserNotFoundException::class);

        self::service(JoinGameHandler::class)(
            new JoinGame($scenario->game()->getId(), 'player', 'nobody', AppFixtures::LION_STARTER_DECK),
        );
    }
}

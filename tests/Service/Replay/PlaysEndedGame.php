<?php

declare(strict_types=1);

namespace App\Tests\Service\Replay;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\ChooseStrongholdProvince;
use App\Message\ConcedeGame;
use App\Message\RevealDynastyCard;
use App\Tests\AbstractKernelTestCase;

/**
 * A game played through the bus until it ends, so that it has events to replay: john and jane join it, choose their
 * stronghold provinces (the decks are shuffled), reveal their dynasty cards (ids drawn by the generator of the game),
 * then jane concedes.
 *
 * @phpstan-require-extends AbstractKernelTestCase
 */
trait PlaysEndedGame
{
    private function playEndedGame(): Game
    {
        $game = $this->createGame() ?? self::fail('The game was not created.');
        $john = $this->createPlayer($game, AppFixtures::LION_STARTER_DECK, 'john') ?? self::fail('john did not join.');
        $jane = $this->createPlayer($game, AppFixtures::CRANE_STARTER_DECK, 'jane') ?? self::fail('jane did not join.');

        $this->chooseStrongholdProvince($game, $john, 'Ancestral Lands');
        $this->chooseStrongholdProvince($game, $jane, 'Entrenched Position');
        foreach ([$john, $jane] as $player) {
            foreach ($player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEDOWN) as $card) {
                $this->messageBus->dispatch(new RevealDynastyCard($game->getId(), $player->getId(), $card->getId()));
            }
        }
        $this->messageBus->dispatch(new ConcedeGame($game->getId(), $jane->getId()));
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());

        return $game;
    }

    private function chooseStrongholdProvince(Game $game, Player $player, string $title): void
    {
        $province = $this->cardRepository->findOneBy([
            'game' => $game,
            'player' => $player,
            'title' => $title,
        ]);
        self::assertInstanceOf(ProvinceCard::class, $province);
        $this->messageBus->dispatch(new ChooseStrongholdProvince($game->getId(), $player->getId(), $province->getId()));
    }
}

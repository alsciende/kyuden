<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Repository\GameRepository;
use App\Service\ControlService;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:cards',
    description: 'List the cards of a game, by player, stack and position (the top card last)',
)]
readonly class GameCardsCommand
{
    private const string INVALID_PLACE = '(invalid)';

    public function __construct(
        private GameRepository $repository,
        private ControlService $controlService,
    ) {
    }

    /**
     * @param list<string> $place
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Game ID')]
        string $id,
        #[Option('Only the cards in this workflow place (e.g. in_play, on_province_faceup); repeatable')]
        array $place = [],
        #[Option('Only the cards owned by this player (user ID)')]
        ?string $player = null,
    ): int {
        $game = $this->repository->find($id);
        if (! $game instanceof Game) {
            throw new \RuntimeException("Game {$id} not found.");
        }

        $rows = [];
        foreach ($this->sortedCards($game) as $card) {
            $cardPlace = $this->placeOf($card);
            if ($place !== [] && ! in_array($cardPlace, $place, true)) {
                continue;
            }
            if ($player !== null && $card->getPlayer()->getUserId() !== $player) {
                continue;
            }

            $controller = $this->controlService->getController($card)->getUserId();
            $rows[] = [
                $card->getId(),
                $card->getLogicalCard()->getId(),
                $card->getLogicalCard()->getName(),
                $card->getPlayer()->getUserId(),
                $controller === $card->getPlayer()->getUserId() ? '' : $controller,
                sprintf('%s %s', $card->getStack()->getType()->name, $card->getStack()->getId()),
                $card->getPosition(),
                $card->getOrientation()->value,
                $cardPlace,
            ];
        }

        $io->table(['Id', 'Code', 'Name', 'Owner', 'Controller', 'Stack', 'Pos.', 'Orientation', 'Place'], $rows);
        $io->text(sprintf('%d card(s)', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * @return list<PhysicalCard>
     */
    private function sortedCards(Game $game): array
    {
        $cards = $game->getPhysicalCards()->getValues();
        usort($cards, fn (PhysicalCard $a, PhysicalCard $b): int => [
            $a->getPlayer()->getUserId(),
            $a->getStack()->getType()->value,
            $a->getStack()->getId(),
            $a->getPosition(),
        ] <=> [
            $b->getPlayer()->getUserId(),
            $b->getStack()->getType()->value,
            $b->getStack()->getId(),
            $b->getPosition(),
        ]);

        return $cards;
    }

    private function placeOf(PhysicalCard $card): string
    {
        try {
            return $card->getCurrentPlace();
        } catch (\LogicException) {
            return self::INVALID_PLACE;
        }
    }
}

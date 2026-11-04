<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LogicalCard;
use App\Entity\PhysicalCard;
use App\Repository\CardRepository;
use App\Repository\LogicalCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;

/**
 * Debugging only: writes the database directly, outside the bus, so that nothing is recorded (no event, no log, no
 * notification) and replaying the events of the game does not reproduce the swap. The card keeps its id, stack,
 * position, orientation and state. Its side may change (a conflict card becoming a dynasty card…), as long as the card
 * of the new side can be where the card is. Not registered in production.
 */
#[WhenNot('prod')]
#[AsCommand(
    name: 'app:card:swap',
    description: 'Debugging: replace the printed card (logical card) of a card of a game, outside the event log',
)]
readonly class CardSwapCommand
{
    public function __construct(
        private CardRepository $cardRepository,
        private LogicalCardRepository $logicalCardRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('ID of the card of the game')]
        string $card,
        #[Argument('Code of the card it becomes')]
        string $code,
    ): int {
        $physicalCard = $this->cardRepository->get($card);
        $logicalCard = $this->logicalCardRepository->find($code);
        if (! $logicalCard instanceof LogicalCard) {
            throw new \RuntimeException("Card {$code} not found.");
        }

        $before = $physicalCard->getLogicalCard();
        if ($before->getSide() === $logicalCard->getSide()) {
            $physicalCard->setLogicalCard($logicalCard);
            if ($physicalCard->getTitle() !== null) {
                $physicalCard->setTitle($logicalCard->getName());
            }
            $this->entityManager->flush();
        } else {
            $this->swapSide($physicalCard, $logicalCard);
        }

        $io->success(sprintf('Card %s: %s (%s) is now %s (%s).', $card, $before->getName(), $before->getId(), $logicalCard->getName(), $logicalCard->getId()));

        return Command::SUCCESS;
    }

    /**
     * The side is the discriminator of the class of the card, which Doctrine does not change on an entity.
     */
    private function swapSide(PhysicalCard $physicalCard, LogicalCard $logicalCard): void
    {
        /** @var class-string<PhysicalCard> $class */
        $class = PhysicalCard::DISCRIMINATOR_MAP[$logicalCard->getSide()->value];
        $stackType = $physicalCard->getStack()->getType();
        if ($class::getPlace($stackType, $physicalCard->getOrientation()) === null) {
            throw new \RuntimeException(sprintf('A %s card cannot be %s in stack %s.', $logicalCard->getSide()->value, $physicalCard->getOrientation()->value, $stackType->name));
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE physical_cards SET side = :side, logical_card_id = :code, title = CASE WHEN title IS NULL THEN NULL ELSE :title END WHERE id = :id',
            [
                'side' => $logicalCard->getSide()->value,
                'code' => $logicalCard->getId(),
                'title' => $logicalCard->getName(),
                'id' => $physicalCard->getId(),
            ],
        );
        $this->entityManager->clear();
    }
}

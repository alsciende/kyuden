<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\ActionRecord;
use App\Entity\Game;
use App\Entity\GameLogEntry;
use App\Entity\Player;
use App\Message\PlayerActionInterface;
use App\Repository\ActionRecordRepository;
use App\Repository\GameLogEntryRepository;
use App\View\Model\GameLogView;
use App\View\Model\LogEntryView;
use App\View\Model\LoggedMessageView;
use Symfony\Component\Serializer\SerializerInterface;

use function Symfony\Component\String\u;

/**
 * Builds the log of a game for a reader, from a given version: the params of its entries the reader does not know are
 * masked (their visibility was computed when they were logged, see App\GameLog\LogParam). A reader who is not a
 * player of the game (null) only sees the public params.
 */
final readonly class GameLogViewFactory
{
    public function __construct(
        private ActionRecordRepository $recordRepository,
        private GameLogEntryRepository $gameLogEntryRepository,
        private SerializerInterface $serializer,
    ) {
    }

    public function create(Game $game, ?Player $reader, int $since = 0): GameLogView
    {
        $entriesByVersion = [];
        foreach ($this->gameLogEntryRepository->findSince($game, $since) as $entry) {
            $entriesByVersion[$entry->getVersion()][] = $this->createEntryView($entry, $reader);
        }

        return new GameLogView(
            gameId: $game->getId(),
            version: $game->getVersion(),
            messages: array_map(
                fn (ActionRecord $record): LoggedMessageView => new LoggedMessageView(
                    version: $record->getVersion(),
                    type: u($record->getName())->afterLast('\\')->snake()->toString(),
                    playerId: $this->getSenderId($record),
                    entries: $entriesByVersion[$record->getVersion()] ?? [],
                ),
                $this->recordRepository->findSince($game, $since),
            ),
        );
    }

    /**
     * The sender of the message: the only field of its payload shown, the payload may hold secret information.
     */
    private function getSenderId(ActionRecord $record): ?string
    {
        $message = $this->serializer->deserialize($record->getPayload(), $record->getName(), 'json');

        return $message instanceof PlayerActionInterface ? $message->getPlayerId() : null;
    }

    /**
     * An entry as the reader sees it.
     */
    public function createEntryView(GameLogEntry $entry, ?Player $reader): LogEntryView
    {
        return new LogEntryView(
            template: $entry->getTemplate(),
            params: array_map(fn (array $param): array => $this->mask($param, $reader), $entry->getParams()),
        );
    }

    /**
     * @param array<string, mixed> $param
     *
     * @return array<string, mixed>
     */
    private function mask(array $param, ?Player $reader): array
    {
        switch ($param['type'] ?? null) {
            case 'card':
                $knowsCard = $this->knows($param['visible_to'] ?? null, $reader);

                return [
                    'type' => 'card',
                    'id' => $knowsCard ? $param['id'] : null,
                    'code' => $knowsCard && $this->knows($param['code_visible_to'] ?? null, $reader) ? $param['code'] : null,
                ];
            case 'value':
                $knowsValue = $this->knows($param['visible_to'] ?? null, $reader);

                return [
                    'type' => 'value',
                    'value' => $knowsValue ? $param['value'] : null,
                    'hidden' => ! $knowsValue,
                ];
            case 'list':
                $items = [];
                foreach (is_array($param['items'] ?? null) ? $param['items'] : [] as $item) {
                    if (is_array($item)) {
                        /** @var array<string, mixed> $item */
                        $items[] = $this->mask($item, $reader);
                    }
                }

                return [
                    'type' => 'list',
                    'items' => $items,
                ];
            default:
                return $param;
        }
    }

    /**
     * A visibility is null for a public param, else the list of the ids of the players who know it.
     */
    private function knows(mixed $visibleTo, ?Player $reader): bool
    {
        return $visibleTo === null || ($reader !== null && is_array($visibleTo) && in_array($reader->getId(), $visibleTo, true));
    }
}

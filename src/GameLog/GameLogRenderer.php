<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\Game;
use App\Repository\LogicalCardRepository;
use App\View\Model\LogEntryView;

use function Symfony\Component\String\u;

/**
 * Renders an entry of the log of a game as English text, for the console: the placeholders of its template are
 * replaced by its params, as the reader sees them (players by user id, cards by name).
 */
final readonly class GameLogRenderer
{
    public function __construct(
        private LogicalCardRepository $logicalCardRepository,
    ) {
    }

    public function render(LogEntryView $entry, Game $game): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $matches): string => $this->renderParam($entry->params[$matches[1]] ?? [], $game),
            $entry->template,
        );
    }

    /**
     * @param array<mixed> $param
     */
    private function renderParam(array $param, Game $game): string
    {
        return match ($param['type'] ?? null) {
            'player' => $this->renderPlayer($param['id'] ?? null, $game),
            'card' => $this->renderCard($param['code'] ?? null, $param['id'] ?? null),
            'stack' => sprintf(
                "%s's %s",
                $this->renderPlayer($param['player_id'] ?? null, $game),
                match ($param['stack_type'] ?? null) {
                    'conflict_draw_deck' => 'conflict deck',
                    'dynasty_draw_deck' => 'dynasty deck',
                    'regular_province' => 'province',
                    'in_play' => 'play area',
                    default => u(is_string($param['stack_type'] ?? null) ? $param['stack_type'] : '')->replace('_', ' ')->toString(),
                },
            ),
            'ring' => sprintf('the %s ring', is_string($param['element'] ?? null) ? $param['element'] : '?'),
            'value' => ($param['hidden'] ?? false) === true ? '?' : match (true) {
                $param['value'] === null => 'none',
                is_bool($param['value']) => $param['value'] ? 'yes' : 'no',
                is_scalar($param['value']) => (string) $param['value'],
                default => '?',
            },
            'list' => ($param['items'] ?? []) === [] ? 'nothing' : implode(', ', array_map(
                fn (mixed $item): string => $this->renderParam(is_array($item) ? $item : [], $game),
                is_array($param['items']) ? $param['items'] : [],
            )),
            default => '?',
        };
    }

    private function renderPlayer(mixed $playerId, Game $game): string
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getId() === $playerId) {
                return $player->getUserId();
            }
        }

        return '?';
    }

    private function renderCard(mixed $code, mixed $id): string
    {
        if (! is_string($code)) {
            return $id === null ? 'a hidden card' : 'a facedown card';
        }

        return $this->logicalCardRepository->find($code)?->getName() ?? $code;
    }
}

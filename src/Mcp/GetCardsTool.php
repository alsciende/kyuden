<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\LogicalCard;
use App\Repository\LogicalCardRepository;
use App\View\LogicalCardViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The cards as printed (see GET /api/cards/{code}), several at once.
 */
final readonly class GetCardsTool
{
    public const int MAX_CODES = 50;

    public function __construct(
        private LogicalCardRepository $logicalCardRepository,
        private LogicalCardViewFactory $logicalCardViewFactory,
        private NormalizerInterface $normalizer,
    ) {
    }

    /**
     * The cards as printed, by the codes the game gives (get_game, wait_for_update): name, side, type, clan, cost,
     * traits, text, skills (military, political, glory), province strength, elements, and the timings of its triggered
     * abilities (action, interrupt, reaction). The engine does not apply their text: you do.
     *
     * @param list<string> $codes the codes of the cards
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'get_cards')]
    public function __invoke(
        #[Schema(items: ['type' => 'string'], minItems: 1, maxItems: self::MAX_CODES)]
        array $codes,
    ): array {
        // the server checks the arguments against their schema (bounds included) before the call
        $codes = array_values(array_unique($codes));
        $cards = $this->logicalCardRepository->findBy(['id' => $codes]);
        $unknown = array_diff($codes, array_map(fn (LogicalCard $card): string => $card->getId(), $cards));
        if ($unknown !== []) {
            throw new ToolCallException(\sprintf('Card not found: [%s]', implode('], [', $unknown)));
        }

        return ['cards' => $this->normalizer->normalize(array_map($this->logicalCardViewFactory->create(...), $cards))];
    }
}

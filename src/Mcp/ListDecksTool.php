<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\User;
use App\Repository\DeckRepository;
use App\View\DeckViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The decks the bot can use (see GET /api/decks).
 */
final readonly class ListDecksTool
{
    public function __construct(
        private DeckRepository $deckRepository,
        private DeckViewFactory $deckViewFactory,
        private NormalizerInterface $normalizer,
        private Security $security,
    ) {
    }

    /**
     * The decks you can join the queue with: your own decks and the starter decks (`<clan>-starter-deck`), each with
     * its id, its name, its owner (null for a starter deck) and its cards (quantity by card code).
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'list_decks')]
    public function __invoke(): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return ['decks' => $this->normalizer->normalize(array_map(
            $this->deckViewFactory->create(...),
            $this->deckRepository->findUsableBy($user),
        ))];
    }
}

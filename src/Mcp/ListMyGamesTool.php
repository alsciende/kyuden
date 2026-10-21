<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\View\Model\UserGameView;
use App\View\UserGameViewFactory;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The doc comment of the method and its params are the description of the tool the agents read.
 *
 * The games of the bot (see GET /api/self/games).
 */
final readonly class ListMyGamesTool
{
    public function __construct(
        private GameRepository $gameRepository,
        private UserGameViewFactory $userGameViewFactory,
        private NormalizerInterface $normalizer,
        private Security $security,
    ) {
    }

    /**
     * Your games, in progress or ended, the last joined first: for each, its id, your player id in it (the sender of
     * your actions), its version, whether it has ended, and the users playing it in player order.
     *
     * @return array<mixed>
     */
    #[McpTool(name: 'list_my_games')]
    public function __invoke(): array
    {
        $userId = $this->security->getUser()?->getUserIdentifier();
        \assert($userId !== null);

        $games = $this->normalizer->normalize(array_map(
            fn (Game $game): UserGameView => $this->userGameViewFactory->create($game, $userId),
            $this->gameRepository->findByUser($userId),
        ));

        return ['games' => $games];
    }
}

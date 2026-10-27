<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\InvitationKind;
use App\Enum\UserType;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Security\AccessTokenIssuer;
use App\Security\InvitationCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Issues and cancels the invitations to a friendly game of an account. A host has one invitation waiting at most:
 * a new one cancels the previous one (and takes the host out of the queue if they waited with it). An invitation for
 * the bot of the host comes with an API key limited to it, returned once; the bot is created the first time
 * ("<name of the host>'s bot") and reused, so that its name stays the same from game to game.
 */
final readonly class InvitationIssuer
{
    public function __construct(
        private InvitationRepository $invitationRepository,
        private UserRepository $userRepository,
        private AccessTokenIssuer $accessTokenIssuer,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{Invitation, ?string} the invitation, and the key of its bot
     */
    public function issue(User $host, InvitationKind $kind): array
    {
        if (! $host->canInvite()) {
            throw new \InvalidArgumentException('Only an account invites.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($host, $kind): array {
            $this->invitationRepository->deleteUnusedOfHost($host);

            $id = Uuid::v4()->toString();
            $now = $this->clock->now();
            if ($kind === InvitationKind::Friend) {
                $invitation = Invitation::forFriend($id, $host, InvitationCode::generate(), $now);
                $this->entityManager->persist($invitation);

                return [$invitation, null];
            }

            $invitation = Invitation::forBot($id, $host, $this->botOf($host), $now);
            $this->entityManager->persist($invitation);

            return [$invitation, $this->accessTokenIssuer->issue($invitation->getBot() ?? throw new \LogicException(), AccessTokenType::ApiKey, $invitation)];
        });
    }

    /**
     * Cancels the invitation of a host that has not led to a game yet.
     *
     * @return bool whether there was one
     */
    public function cancel(User $host): bool
    {
        $invitation = $this->invitationRepository->findUnusedOfHost($host);
        if (! $invitation instanceof Invitation) {
            return false;
        }

        $this->entityManager->remove($invitation);
        $this->entityManager->flush();

        return true;
    }

    private function botOf(User $host): User
    {
        $bot = $this->userRepository->findOneBy(['owner' => $host, 'type' => UserType::Bot], ['id' => 'ASC']);
        if ($bot instanceof User) {
            return $bot;
        }

        $bot = new User(Uuid::v4()->toString(), sprintf("%s's bot", $host->getName()), UserType::Bot, $host);
        $this->entityManager->persist($bot);

        return $bot;
    }
}

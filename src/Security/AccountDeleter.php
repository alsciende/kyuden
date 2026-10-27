<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Candidate;
use App\Entity\User;
use App\Message\ConcedeGame;
use App\Repository\AccessTokenRepository;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use App\Repository\GameRepository;
use App\Repository\InvitationRepository;
use App\Repository\LinkedAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Deletes an account, and the bots it owns: the user stays, anonymized with the date of the deletion (User::anonymize()),
 * so that the games keep their players and their opponents their history. It leaves the queue, concedes the games it
 * plays (its opponents win), then its invitations, its tokens, its linked identities and its decks are deleted (those
 * played in a game only lose their name).
 */
readonly class AccountDeleter
{
    public const string CONCESSION_REASON = 'The account was deleted.';

    public function __construct(
        private UserRepository $userRepository,
        private CandidateRepository $candidateRepository,
        private GameRepository $gameRepository,
        private AccessTokenRepository $accessTokenRepository,
        private LinkedAccountRepository $linkedAccountRepository,
        private InvitationRepository $invitationRepository,
        private DeckRepository $deckRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    public function delete(User $user): void
    {
        $users = [$user, ...$this->userRepository->findBy(['owner' => $user])];

        $this->candidateRepository->withQueueLock(function () use ($users): void {
            foreach ($users as $user) {
                $candidate = $this->candidateRepository->findOneBy(['user' => $user]);
                if ($candidate instanceof Candidate) {
                    $this->entityManager->remove($candidate);
                }
            }
            $this->entityManager->flush();
        });

        // each concession is handled in its own transaction, the game locked
        foreach ($users as $user) {
            foreach ($this->gameRepository->findInProgressPlayersOf($user->getId()) as $player) {
                $this->bus->dispatch(new ConcedeGame($player['gameId'], $player['playerId'], self::CONCESSION_REASON));
            }
        }

        $deletedAt = $this->clock->now();
        $this->entityManager->wrapInTransaction(function () use ($users, $deletedAt): void {
            foreach ($users as $user) {
                $this->invitationRepository->deleteByHost($user);
                $this->accessTokenRepository->deleteByUser($user);
                $this->linkedAccountRepository->deleteByUser($user);
                $this->deckRepository->deleteByOwner($user);
                $user->anonymize($deletedAt);
            }
        });
    }
}

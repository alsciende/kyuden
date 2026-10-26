<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\LinkedAccount;
use App\Entity\User;
use App\Enum\OAuthProvider;
use App\Exception\Api\IdentityAlreadyLinkedException;
use App\Exception\Api\ProviderAlreadyLinkedException;
use App\Repository\LinkedAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Links an account to an identity at a provider: an identity to one account at most, an account to one identity of
 * each provider at most. Linking the identity already linked changes nothing. The caller flushes; the uniqueness is
 * checked here, and by the database when two requests link at once.
 */
readonly class AccountLinker
{
    public function __construct(
        private LinkedAccountRepository $linkedAccountRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ProviderAlreadyLinkedException
     * @throws IdentityAlreadyLinkedException
     */
    public function link(User $user, OAuthProvider $provider, string $subject): LinkedAccount
    {
        $linked = $this->linkedAccountRepository->findOneByUserAndProvider($user, $provider);
        if ($linked instanceof LinkedAccount) {
            if ($linked->getSubject() !== $subject) {
                throw new ProviderAlreadyLinkedException($provider);
            }

            return $linked;
        }
        if ($this->linkedAccountRepository->findOneByIdentity($provider, $subject) instanceof LinkedAccount) {
            throw new IdentityAlreadyLinkedException($provider);
        }

        $linked = new LinkedAccount($user, $provider, $subject, $this->clock->now());
        $this->entityManager->persist($linked);

        return $linked;
    }
}

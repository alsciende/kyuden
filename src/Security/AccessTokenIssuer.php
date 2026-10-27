<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AccessToken;
use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\AccessTokenType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Issues the access tokens: a random secret of 256 bits, prefixed so that a leaked token is recognized (secret
 * scanners). The token is returned once; only its hash is stored. The caller flushes.
 */
readonly class AccessTokenIssuer
{
    public const string PREFIX = 'l5r_';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function issue(User $user, AccessTokenType $type, ?Invitation $invitation = null): string
    {
        $token = self::PREFIX . bin2hex(random_bytes(32));
        $this->entityManager->persist(new AccessToken($user, $token, $type, $invitation));

        return $token;
    }
}

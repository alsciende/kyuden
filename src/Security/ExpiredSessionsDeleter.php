<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Deletes the sessions of the accounts that have expired (AccessToken::isExpired()), which AccessTokenHandler refuses
 * already: the table does not keep them. The sessions of the guests and the API keys never expire.
 */
final readonly class ExpiredSessionsDeleter
{
    public function __construct(
        private AccessTokenRepository $accessTokenRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int the number of sessions deleted
     */
    public function deleteAll(): int
    {
        return $this->accessTokenRepository->deleteSessionsOfAccountsUnusedSince(
            $this->clock->now()->modify(sprintf('-%d seconds', AccessToken::SESSION_EXPIRES_AFTER)),
        );
    }
}

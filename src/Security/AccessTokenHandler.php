<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\AccessTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates the user of an access token (App\Entity\AccessToken), found by the hash of the token. The user is
 * loaded with the token, so that authenticating costs a single query.
 */
readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private AccessTokenRepository $accessTokenRepository,
    ) {
    }

    #[\Override]
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $user = $this->accessTokenRepository->findOneByToken($accessToken)?->getUser()
            ?? throw new BadCredentialsException('Invalid access token.');

        return new UserBadge($user->getUserIdentifier(), static fn (): User => $user);
    }
}

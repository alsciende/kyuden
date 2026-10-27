<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Invitation;
use App\Entity\User;
use App\Repository\AccessTokenRepository;
use App\Repository\GameRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates the user of an access token (App\Entity\AccessToken), found by the hash of the token. The user is
 * loaded with the token, so that authenticating costs a single query. The key of an invitation for a bot is valid
 * while the invitation waits for the bot, then until the end of the game it led to. The token is kept for the request
 * (CurrentAccessToken).
 */
readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private AccessTokenRepository $accessTokenRepository,
        private GameRepository $gameRepository,
        private CurrentAccessToken $currentAccessToken,
        private ClockInterface $clock,
    ) {
    }

    #[\Override]
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->accessTokenRepository->findOneByToken($accessToken);
        $invitation = $token?->getInvitation();
        if ($token === null || ($invitation instanceof Invitation && ! $this->isValid($invitation))) {
            throw new BadCredentialsException('Invalid access token.');
        }

        $this->currentAccessToken->set($token);
        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn (): User => $user);
    }

    private function isValid(Invitation $invitation): bool
    {
        $gameId = $invitation->getGameId();
        if ($gameId === null) {
            return $invitation->isPending($this->clock->now());
        }

        return $this->gameRepository->hasEnded($gameId) === false;
    }
}

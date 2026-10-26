<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\User;
use App\Enum\OAuthProvider;
use App\Enum\OAuthPurpose;
use App\Exception\Api\InvalidOAuthStateException;
use App\Exception\Api\OAuthFailedException;
use App\Exception\Api\OAuthProviderUnavailableException;
use App\View\Model\OAuthAuthorizationView;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The authorization code grant with PKCE (RFC 6749, RFC 7636), the application being the confidential client: the
 * web client sends the human to the provider, the provider sends them back to the web client with a code, which the
 * web client sends to the API; the API exchanges it (with its secret and the code verifier) and reads the identifier
 * of the identity. Nothing the provider issues reaches the web client.
 *
 * The state is a random secret of 256 bits, used once, kept for 10 minutes with what the flow was started for (the
 * provider, the purpose, the user linking their account, the redirect URI and the code verifier): a state issued to
 * link an account cannot sign in, nor link the account of another user. The web client also checks that the state
 * coming back is the one it started with, so that a human cannot be signed in to the account of someone else.
 */
readonly class OAuthFlow
{
    public const int STATE_LIFETIME = 600;

    public function __construct(
        private OAuthProviderFactory $providerFactory,
        #[Autowire(service: 'cache.oauth_state')]
        private CacheItemPoolInterface $states,
    ) {
    }

    /**
     * @param User|null $user the user linking their account, null to sign in
     */
    public function begin(OAuthProvider $provider, OAuthPurpose $purpose, ?User $user, string $redirectUri): OAuthAuthorizationView
    {
        $client = $this->providerFactory->create($provider, $redirectUri);
        $state = bin2hex(random_bytes(32));
        $url = $client->getAuthorizationUrl(['state' => $state]);

        $item = $this->states->getItem(self::key($state));
        $item->set([
            'provider' => $provider->value,
            'purpose' => $purpose->value,
            'user_id' => $user?->getId(),
            'redirect_uri' => $redirectUri,
            'pkce_code' => $client->getPkceCode(),
        ]);
        $item->expiresAfter(self::STATE_LIFETIME);
        $this->states->save($item);

        return new OAuthAuthorizationView($url, $state);
    }

    /**
     * Exchanges the code the provider sent back, once.
     *
     * @param User|null $user the user linking their account, null to sign in
     *
     * @return string the identifier of the identity at the provider
     *
     * @throws InvalidOAuthStateException
     * @throws OAuthFailedException
     * @throws OAuthProviderUnavailableException
     */
    public function complete(OAuthProvider $provider, OAuthPurpose $purpose, #[\SensitiveParameter] string $state, #[\SensitiveParameter] string $code, ?User $user): string
    {
        $key = self::key($state);
        $item = $this->states->getItem($key);
        if (! $item->isHit()) {
            throw new InvalidOAuthStateException();
        }
        $this->states->deleteItem($key);

        /** @var array{provider: string, purpose: string, user_id: string|null, redirect_uri: string, pkce_code: string|null} $flow */
        $flow = $item->get();
        if ($flow['provider'] !== $provider->value || $flow['purpose'] !== $purpose->value || $flow['user_id'] !== $user?->getId()) {
            throw new InvalidOAuthStateException();
        }

        $client = $this->providerFactory->create($provider, $flow['redirect_uri']);
        if ($flow['pkce_code'] !== null) {
            $client->setPkceCode($flow['pkce_code']);
        }
        try {
            $token = $client->getAccessToken('authorization_code', ['code' => $code]);
            \assert($token instanceof AccessToken);
            $subject = $client->getResourceOwner($token)->getId();
        } catch (IdentityProviderException|\UnexpectedValueException $exception) {
            throw new OAuthFailedException($provider, $exception);
        } catch (GuzzleException $exception) {
            throw new OAuthProviderUnavailableException($provider, $exception);
        }
        if (! is_scalar($subject) || (string) $subject === '') {
            throw new OAuthFailedException($provider);
        }

        return (string) $subject;
    }

    private static function key(#[\SensitiveParameter] string $state): string
    {
        return hash('sha256', $state);
    }
}

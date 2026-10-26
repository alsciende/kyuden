<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Enum\OAuthProvider;
use App\Security\OAuth\Provider\Discord;
use App\Security\OAuth\Provider\Google;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the clients of the providers of identities (league/oauth2-client), from the credentials of the application
 * at each provider (`OAUTH_<PROVIDER>_CLIENT_ID` and `_CLIENT_SECRET`): a provider without a client id is disabled.
 * Their requests go through the HTTP client `app.oauth.http_client` (Guzzle), replaced in the tests.
 */
readonly class OAuthProviderFactory
{
    /**
     * @param array<string, array{client_id: string, client_secret: string}> $credentials by provider
     */
    public function __construct(
        private array $credentials,
        #[Autowire(service: 'app.oauth.http_client')]
        private ClientInterface $httpClient,
    ) {
    }

    public function isEnabled(OAuthProvider $provider): bool
    {
        return ($this->credentials[$provider->value]['client_id'] ?? '') !== '';
    }

    /**
     * @return list<OAuthProvider>
     */
    public function getEnabledProviders(): array
    {
        return array_values(array_filter(OAuthProvider::cases(), $this->isEnabled(...)));
    }

    /**
     * @param string $redirectUri where the provider sends the human back, as registered at the provider
     */
    public function create(OAuthProvider $provider, string $redirectUri): AbstractProvider
    {
        if (! $this->isEnabled($provider)) {
            throw new \LogicException("The provider {$provider->value} is disabled.");
        }

        $options = [
            'clientId' => $this->credentials[$provider->value]['client_id'],
            'clientSecret' => $this->credentials[$provider->value]['client_secret'],
            'redirectUri' => $redirectUri,
        ];
        $collaborators = ['httpClient' => $this->httpClient];

        return match ($provider) {
            OAuthProvider::Discord => new Discord($options, $collaborators),
            OAuthProvider::Google => new Google($options, $collaborators),
        };
    }
}

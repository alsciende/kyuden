<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LinkedAccount;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\OAuthProvider;
use App\Enum\OAuthPurpose;
use App\Exception\Api\IdentityAlreadyLinkedException;
use App\Exception\Api\InvalidOAuthStateException;
use App\Exception\Api\OAuthFailedException;
use App\Exception\Api\OAuthProviderUnavailableException;
use App\Exception\Api\ProviderAlreadyLinkedException;
use App\Repository\LinkedAccountRepository;
use App\Security\AccessTokenIssuer;
use App\Security\OAuth\AccountLinker;
use App\Security\OAuth\OAuthFlow;
use App\Security\OAuth\OAuthProviderFactory;
use App\View\Model\AccountView;
use App\View\Model\LinkedAccountView;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Signing in with a provider of identities (OAuth2: Google, Discord), once the account is linked to an
 * identity there. An account is created with a username and a password only (`POST /api/accounts`); signed in, its
 * human links it to their identities (`PUT /api/self/linked-accounts/{provider}`), then signs in with any of them
 * (`POST /api/oauth/{provider}/sessions`). Both start with `POST /api/oauth/{provider}/authorizations`, which gives
 * the URL of the provider; the provider sends the human back to the web client (`/oauth/{provider}/callback`), which
 * sends the code and the state to the API (OAuthFlow). Errors are problem details (RFC 9457).
 */
class ApiOAuthController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly OAuthProviderFactory $providerFactory,
        private readonly OAuthFlow $flow,
        private readonly AccountLinker $accountLinker,
        private readonly LinkedAccountRepository $linkedAccountRepository,
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly EntityManagerInterface $entityManager,
        #[Target('login_ip')]
        private readonly RateLimiterFactoryInterface $ipLimiter,
    ) {
    }

    /**
     * The providers the humans can link their account to and sign in with (public).
     */
    #[Route('/api/oauth/providers', name: 'app_api_oauth_providers', methods: ['GET'])]
    public function providers(): JsonResponse
    {
        return $this->json(array_map(
            static fn (OAuthProvider $provider): string => $provider->value,
            $this->providerFactory->getEnabledProviders(),
        ));
    }

    /**
     * Starts a flow, `{"purpose": "sign_in" | "link"}`: 201 with the URL of the provider and the state. To link, the
     * human sends the token of their account. Public.
     */
    #[Route('/api/oauth/{provider}/authorizations', name: 'app_api_oauth_authorizations_create', methods: ['POST'])]
    public function authorize(Request $request, string $provider): Response
    {
        $oauthProvider = $this->findEnabledProvider($provider);
        if (! $oauthProvider instanceof OAuthProvider) {
            return $this->unknownProvider($provider);
        }
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }
        $purpose = is_string($body['purpose'] ?? null) && array_keys($body) === ['purpose'] ? OAuthPurpose::tryFrom($body['purpose']) : null;
        if (! $purpose instanceof OAuthPurpose) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a "purpose": "sign_in" or "link".', [
                'violations' => [['property_path' => 'purpose', 'message' => 'Choose one of: sign_in, link.']],
            ]);
        }

        $user = null;
        if ($purpose === OAuthPurpose::Link) {
            $user = $this->getUser();
            if (! $user instanceof User) {
                return $this->problem(Response::HTTP_UNAUTHORIZED, 'authentication_required', 'Sign in to the account to link.');
            }
            if (! $user->canLinkAccounts()) {
                return $this->accountRequired();
            }
        }

        return $this->json($this->flow->begin($oauthProvider, $purpose, $user, $this->redirectUri($request, $oauthProvider)), Response::HTTP_CREATED);
    }

    /**
     * Signs in with the identity the provider sent back, `{"code": ..., "state": ...}`: 201 with a new session token,
     * like `POST /api/sessions`; 401 `not_linked` when no account is linked to that identity. Each attempt counts
     * against the limit of its IP. Public.
     */
    #[Route('/api/oauth/{provider}/sessions', name: 'app_api_oauth_sessions_create', methods: ['POST'])]
    public function signIn(Request $request, string $provider): Response
    {
        $oauthProvider = $this->findEnabledProvider($provider);
        if (! $oauthProvider instanceof OAuthProvider) {
            return $this->unknownProvider($provider);
        }
        $callback = $this->readCallback($request);
        if ($callback instanceof Response) {
            return $callback;
        }

        $limit = $this->ipLimiter->create($request->getClientIp())->consume();
        if (! $limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            $response = $this->problem(Response::HTTP_TOO_MANY_REQUESTS, 'too_many_attempts', "Too many attempts to sign in: try again in {$retryAfter} seconds.");
            $response->headers->set('Retry-After', (string) $retryAfter);

            return $response;
        }

        try {
            $subject = $this->flow->complete($oauthProvider, OAuthPurpose::SignIn, $callback['state'], $callback['code'], null);
        } catch (InvalidOAuthStateException|OAuthFailedException|OAuthProviderUnavailableException $exception) {
            return $this->flowProblem($exception);
        }

        $linked = $this->linkedAccountRepository->findOneByIdentity($oauthProvider, $subject);
        if (! $linked instanceof LinkedAccount) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'not_linked', "No account is linked to this {$oauthProvider->value} account: sign in with your username, then link it from your account.");
        }
        $user = $linked->getUser();
        $token = $this->accessTokenIssuer->issue($user, AccessTokenType::Session);
        $this->entityManager->flush();

        $username = $user->getUsername();
        \assert($username !== null, 'only an account is linked');

        return $this->json(new AccountView($user->getId(), $user->getName(), $username, $user->getPreferredClan(), $user->getSettings(), $token), Response::HTTP_CREATED);
    }

    /**
     * The identities linked to the account of the authenticated user, by provider.
     */
    #[Route('/api/self/linked-accounts', name: 'app_api_self_linked_accounts', methods: ['GET'])]
    public function linkedAccounts(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map(
            static fn (LinkedAccount $linked): LinkedAccountView => new LinkedAccountView($linked->getProvider(), $linked->getLinkedAt()),
            $this->linkedAccountRepository->findByUser($user),
        ));
    }

    /**
     * Links the account of the authenticated user to the identity the provider sent back, `{"code": ..., "state": ...}`:
     * 201 with the linked account (200 when that identity was linked already); 409 when the account is linked to
     * another identity of the provider (`provider_already_linked`) or the identity to another account
     * (`identity_already_linked`).
     */
    #[Route('/api/self/linked-accounts/{provider}', name: 'app_api_self_linked_accounts_link', methods: ['PUT'])]
    public function link(Request $request, string $provider, #[CurrentUser] User $user): Response
    {
        $oauthProvider = $this->findEnabledProvider($provider);
        if (! $oauthProvider instanceof OAuthProvider) {
            return $this->unknownProvider($provider);
        }
        if (! $user->canLinkAccounts()) {
            return $this->accountRequired();
        }
        $callback = $this->readCallback($request);
        if ($callback instanceof Response) {
            return $callback;
        }

        try {
            $subject = $this->flow->complete($oauthProvider, OAuthPurpose::Link, $callback['state'], $callback['code'], $user);
            $linked = $this->accountLinker->link($user, $oauthProvider, $subject);
            $created = $this->entityManager->getUnitOfWork()->isScheduledForInsert($linked);
            $this->entityManager->flush();
        } catch (InvalidOAuthStateException|OAuthFailedException|OAuthProviderUnavailableException $exception) {
            return $this->flowProblem($exception);
        } catch (ProviderAlreadyLinkedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'provider_already_linked', $exception->getMessage());
        } catch (IdentityAlreadyLinkedException $exception) {
            return $this->problem(Response::HTTP_CONFLICT, 'identity_already_linked', $exception->getMessage());
        } catch (UniqueConstraintViolationException) {
            // linked at once by another request
            return $this->problem(Response::HTTP_CONFLICT, 'identity_already_linked', "This {$oauthProvider->value} account is already linked.");
        }

        return $this->json(new LinkedAccountView($linked->getProvider(), $linked->getLinkedAt()), $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Unlinks the account of the authenticated user from their identity at a provider: 204; 404 `not_linked`. A
     * disabled provider can still be unlinked.
     */
    #[Route('/api/self/linked-accounts/{provider}', name: 'app_api_self_linked_accounts_unlink', methods: ['DELETE'])]
    public function unlink(string $provider, #[CurrentUser] User $user): Response
    {
        $oauthProvider = OAuthProvider::tryFrom($provider);
        if (! $oauthProvider instanceof OAuthProvider) {
            return $this->unknownProvider($provider);
        }
        $linked = $this->linkedAccountRepository->findOneByUserAndProvider($user, $oauthProvider);
        if (! $linked instanceof LinkedAccount) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'not_linked', "The account is not linked to a {$oauthProvider->value} account.");
        }
        $this->entityManager->remove($linked);
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function findEnabledProvider(string $provider): ?OAuthProvider
    {
        $oauthProvider = OAuthProvider::tryFrom($provider);

        return $oauthProvider instanceof OAuthProvider && $this->providerFactory->isEnabled($oauthProvider) ? $oauthProvider : null;
    }

    /**
     * Where the provider sends the human back: a page of the web client, on the origin of the request.
     */
    private function redirectUri(Request $request, OAuthProvider $provider): string
    {
        return "{$request->getSchemeAndHttpHost()}/oauth/{$provider->value}/callback";
    }

    /**
     * @return array{code: string, state: string}|Response the code and the state the provider sent back, or the problem
     */
    private function readCallback(Request $request): array|Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $code = $body['code'] ?? null;
        $state = $body['state'] ?? null;
        if (! is_string($code) || $code === '' || ! is_string($state) || $state === '' || array_diff(array_keys($body), ['code', 'state']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "code" and a string "state".', [
                'violations' => [['property_path' => 'code', 'message' => 'This field must be a string.'], ['property_path' => 'state', 'message' => 'This field must be a string.']],
            ]);
        }

        return ['code' => $code, 'state' => $state];
    }

    private function flowProblem(InvalidOAuthStateException|OAuthFailedException|OAuthProviderUnavailableException $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof InvalidOAuthStateException => $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_state', $exception->getMessage()),
            $exception instanceof OAuthFailedException => $this->problem(Response::HTTP_BAD_REQUEST, 'authorization_failed', $exception->getMessage()),
            $exception instanceof OAuthProviderUnavailableException => $this->problem(Response::HTTP_BAD_GATEWAY, 'provider_unavailable', $exception->getMessage()),
        };
    }

    private function unknownProvider(string $provider): JsonResponse
    {
        return $this->problem(Response::HTTP_NOT_FOUND, 'unknown_provider', "Unknown provider {$provider}.");
    }

    private function accountRequired(): JsonResponse
    {
        return $this->problem(Response::HTTP_FORBIDDEN, 'account_required', 'Only an account signed in to with a username is linked: create one first.');
    }
}

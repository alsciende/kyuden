<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Repository\AccessTokenRepository;
use App\Repository\UserRepository;
use App\Security\AccessTokenIssuer;
use App\View\Model\AccountView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The sessions of the humans: signing in with a username (whatever its case) and a password gives a new session
 * token (`POST /api/sessions`, public); signing out revokes the token of the request (`DELETE
 * /api/sessions/current`). Each attempt to sign in counts against the limits of its IP and of its username; a success
 * resets the count of the username. Errors are problem details (RFC 9457).
 */
class ApiSessionsController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly EntityManagerInterface $entityManager,
        #[Target('login_username')]
        private readonly RateLimiterFactoryInterface $usernameLimiter,
        #[Target('login_ip')]
        private readonly RateLimiterFactoryInterface $ipLimiter,
    ) {
    }

    #[Route('/api/sessions', name: 'app_api_sessions_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;
        if (! is_string($username) || ! is_string($password) || array_diff(array_keys($body), ['username', 'password']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "username" and a string "password".', [
                'violations' => [['property_path' => 'username', 'message' => 'This field must be a string.'], ['property_path' => 'password', 'message' => 'This field must be a string.']],
            ]);
        }

        $usernameLimiter = $this->usernameLimiter->create(User::canonicalizeUsername($username));
        foreach ([$this->ipLimiter->create($request->getClientIp()), $usernameLimiter] as $limiter) {
            $limit = $limiter->consume();
            if (! $limit->isAccepted()) {
                return $this->tooManyAttempts($limit);
            }
        }

        $user = $this->userRepository->findOneByUsername($username);
        if (! $user instanceof User || ! $this->passwordHasher->isPasswordValid($user, $password)) {
            if (! $user instanceof User) {
                // as long as with a user: whether the username exists does not show
                $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($password);
            }

            return $this->problem(Response::HTTP_UNAUTHORIZED, 'invalid_credentials', 'Unknown username or wrong password.');
        }
        $usernameLimiter->reset();

        if ($this->passwordHasher->needsRehash($user)) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        }
        $token = $this->accessTokenIssuer->issue($user, AccessTokenType::Session);
        $this->entityManager->flush();

        $registeredUsername = $user->getUsername();
        \assert($registeredUsername !== null);

        return $this->json(new AccountView($user->getId(), $user->getName(), $registeredUsername, $token), Response::HTTP_CREATED);
    }

    #[Route('/api/sessions/current', name: 'app_api_sessions_delete', methods: ['DELETE'])]
    public function delete(Request $request): Response
    {
        $token = preg_match('/^Bearer\s+(\S+)$/', (string) $request->headers->get('Authorization', ''), $matches) === 1 ? $matches[1] : '';
        $accessToken = $this->accessTokenRepository->findOneByToken($token);
        \assert($accessToken instanceof AccessToken, 'the request was authenticated by this token');

        if ($accessToken->getType() !== AccessTokenType::Session) {
            return $this->problem(Response::HTTP_CONFLICT, 'not_a_session_token', 'Only a session token is revoked by signing out.');
        }
        $this->entityManager->remove($accessToken);
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function tooManyAttempts(RateLimit $limit): Response
    {
        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
        $response = $this->problem(Response::HTTP_TOO_MANY_REQUESTS, 'too_many_attempts', "Too many attempts to sign in: try again in {$retryAfter} seconds.");
        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }
}

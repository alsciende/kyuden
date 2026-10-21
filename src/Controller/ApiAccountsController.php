<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Exception\Api\UsernameTakenException;
use App\Security\AccessTokenIssuer;
use App\Security\AccountRegistrar;
use App\Model\AccountRequest;
use App\View\Model\AccountView;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The accounts the humans sign in to (`{"username": ..., "password": ..., "clan": ...}`, the preferred clan, a Great
 * Clan). A guest sends their token and keeps their user, name included; anyone else also sends their name at court
 * (`"name"`) and gets a new user. The account gets a copy of the starter deck of its clan. The response holds a new
 * session token. Public. Errors are problem details (RFC 9457).
 */
class ApiAccountsController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly AccountRegistrar $accountRegistrar,
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/accounts', name: 'app_api_accounts_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): Response
    {
        if ($user instanceof User && ! $user->isGuest()) {
            return $this->problem(Response::HTTP_CONFLICT, 'already_registered', 'The user already has an account.');
        }

        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $fields = $user instanceof User ? ['username', 'password', 'clan'] : ['username', 'password', 'clan', 'name'];
        $violations = $this->getStructureViolations($body, $fields);
        if ($violations !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Invalid account.', ['violations' => $violations]);
        }
        /** @var array{username: string, password: string, clan: string, name?: string} $body */
        $accountRequest = new AccountRequest($body['username'], $body['password'], $body['clan'], isset($body['name']) ? trim($body['name']) : null);

        $constraintViolations = $this->validator->validate($accountRequest, groups: $user instanceof User ? ['Default'] : ['Default', 'new_user']);
        if ($constraintViolations->count() > 0) {
            return $this->validationProblem($constraintViolations);
        }

        try {
            $account = $this->accountRegistrar->register($user, $accountRequest->username, $accountRequest->password, $accountRequest->getClan(), $accountRequest->name);
            $token = $this->accessTokenIssuer->issue($account, AccessTokenType::Session);
            $this->entityManager->flush();
        } catch (UsernameTakenException|UniqueConstraintViolationException) {
            return $this->usernameTaken($accountRequest);
        }

        $username = $account->getUsername();
        \assert($username !== null);

        return $this->json(new AccountView($account->getId(), $account->getName(), $username, $account->getPreferredClan(), $token), Response::HTTP_CREATED);
    }

    private function usernameTaken(AccountRequest $accountRequest): JsonResponse
    {
        return $this->validationProblem(new ConstraintViolationList([
            new ConstraintViolation('This username is taken.', null, [], $accountRequest, 'username', $accountRequest->username),
        ]));
    }

    /**
     * @param array<mixed> $body
     * @param list<string> $fields
     *
     * @return list<array{property_path: string, message: string}>
     */
    private function getStructureViolations(array $body, array $fields): array
    {
        $violations = [];
        foreach (array_diff(array_map(strval(...), array_keys($body)), $fields) as $field) {
            $violations[] = ['property_path' => $field, 'message' => 'This field is not expected.'];
        }
        foreach ($fields as $field) {
            if (! is_string($body[$field] ?? null)) {
                $violations[] = ['property_path' => $field, 'message' => 'This field must be a string.'];
            }
        }

        return $violations;
    }
}

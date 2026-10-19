<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Enum\UserType;
use App\Security\AccessTokenIssuer;
use App\View\Model\GuestView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Guest accounts: anyone gets a human account by choosing a name (`{"name": ...}`), with a session token, to try the
 * game without signing up. The account is not linked to any identity yet. Public. Errors are problem details
 * (RFC 9457).
 */
class ApiGuestsController extends AbstractController
{
    use ProblemResponseTrait;

    public const int MAX_NAME_LENGTH = 64;

    public const string ID_PREFIX = 'guest-';

    public function __construct(
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/guests', name: 'app_api_guests_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $name = $body['name'] ?? null;
        $name = is_string($name) ? trim($name) : null;
        if ($name === null || $name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH || array_diff(array_keys($body), ['name']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a string "name".', [
                'violations' => [['property_path' => 'name', 'message' => sprintf('The name of the guest, from 1 to %d characters.', self::MAX_NAME_LENGTH)]],
            ]);
        }

        $user = new User(self::ID_PREFIX . Uuid::v4()->toString(), $name, UserType::Human);
        $this->entityManager->persist($user);
        $token = $this->accessTokenIssuer->issue($user, AccessTokenType::Session);
        $this->entityManager->flush();

        return $this->json(new GuestView($user->getId(), $user->getName(), $token), Response::HTTP_CREATED);
    }
}

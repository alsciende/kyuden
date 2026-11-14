<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\BannerPattern;
use App\Enum\CardDisplay;
use App\Enum\Clan;
use App\Enum\Mon;
use App\Enum\Theme;
use App\Model\AccountRequest;
use App\Notifier\SubscriptionFactory;
use App\Repository\GameRepository;
use App\Security\AccountDeleter;
use App\Service\Nobori\NoboriCollection;
use App\View\Model\NoboriCollectionView;
use App\View\Model\SelfView;
use App\View\UserGameViewFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The authenticated user: their identifier, their name at court, their preferred clan, their settings and their nobori
 * (which they change, `PATCH {"name": ..., "preferred_clan": ..., "settings": {"card_display": ..., "theme": ...},
 * "banner": {"mon": ..., "pattern": ...}}`, any of them; a setting or a part of the nobori left out is kept; a mon or a
 * pattern they have not earned is refused), what they may choose for their nobori and their username, and their games (in progress or ended, the last joined first), so that a user matched by
 * the queue finds the id of their game; and their subscription to the Mercure hub. A human deletes their account
 * (`DELETE`). Errors are problem details (RFC 9457).
 */
class ApiSelfController extends AbstractController
{
    use ProblemResponseTrait;

    private const array UPDATABLE_FIELDS = ['name', 'preferred_clan', 'settings', 'banner'];

    private const array STRING_FIELDS = ['name', 'preferred_clan'];

    private const array SETTINGS = ['card_display', 'theme'];

    private const array BANNER = ['mon', 'pattern'];

    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly UserGameViewFactory $userGameViewFactory,
        private readonly AccountDeleter $accountDeleter,
        private readonly NoboriCollection $noboriCollection,
    ) {
    }

    #[Route('/api/self', name: 'app_api_self', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(new SelfView(
            $user->getId(),
            $user->getName(),
            $user->getUsername(),
            $user->getPreferredClan(),
            $user->getSettings(),
            $user->getBanner(),
            new NoboriCollectionView($this->noboriCollection->mons($user), $this->noboriCollection->patterns($user)),
        ));
    }

    #[Route('/api/self', name: 'app_api_self_update', methods: ['PATCH'])]
    public function update(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $structureViolations = [];
        foreach (array_diff(array_map(strval(...), array_keys($body)), self::UPDATABLE_FIELDS) as $field) {
            $structureViolations[] = ['property_path' => $field, 'message' => 'This field is not expected.'];
        }
        foreach (self::STRING_FIELDS as $field) {
            if (array_key_exists($field, $body) && ! is_string($body[$field])) {
                $structureViolations[] = ['property_path' => $field, 'message' => 'This field must be a string.'];
            }
        }
        // the objects of strings, of which any field may be left out
        foreach (['settings' => self::SETTINGS, 'banner' => self::BANNER] as $object => $fields) {
            if (! array_key_exists($object, $body)) {
                continue;
            }
            if (! is_array($body[$object]) || ($body[$object] !== [] && array_is_list($body[$object]))) {
                $structureViolations[] = ['property_path' => $object, 'message' => 'This field must be an object.'];

                continue;
            }
            foreach (array_diff(array_map(strval(...), array_keys($body[$object])), $fields) as $field) {
                $structureViolations[] = ['property_path' => "{$object}.{$field}", 'message' => 'This field is not expected.'];
            }
            foreach ($fields as $field) {
                if (array_key_exists($field, $body[$object]) && ! is_string($body[$object][$field])) {
                    $structureViolations[] = ['property_path' => "{$object}.{$field}", 'message' => 'This field must be a string.'];
                }
            }
        }
        if ($body === []) {
            $structureViolations[] = ['property_path' => '', 'message' => sprintf('The body must have one of: %s.', implode(', ', self::UPDATABLE_FIELDS))];
        }
        if ($structureViolations !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'Invalid changes of the user.', ['violations' => $structureViolations]);
        }
        /** @var array{name?: string, preferred_clan?: string, settings?: array{card_display?: string, theme?: string}, banner?: array{mon?: string, pattern?: string}} $body */
        $name = isset($body['name']) ? trim($body['name']) : null;
        $clan = $body['preferred_clan'] ?? null;
        $cardDisplay = $body['settings']['card_display'] ?? null;
        $theme = $body['settings']['theme'] ?? null;
        $mon = $body['banner']['mon'] ?? null;
        $pattern = $body['banner']['pattern'] ?? null;

        $validator = $this->validator->startContext();
        if ($name !== null) {
            $validator->atPath('name')->validate($name, [new Assert\NotBlank(), new Assert\Length(max: User::MAX_NAME_LENGTH)]);
        }
        if ($clan !== null) {
            $validator->atPath('preferred_clan')->validate($clan, new Assert\Choice(
                choices: AccountRequest::getClanValues(),
                message: 'Choose one of the Great Clans: {{ choices }}.',
            ));
        }
        if ($cardDisplay !== null) {
            $validator->atPath('settings.card_display')->validate($cardDisplay, new Assert\Choice(
                choices: array_column(CardDisplay::cases(), 'value'),
                message: 'Choose one of: {{ choices }}.',
            ));
        }
        if ($theme !== null) {
            $validator->atPath('settings.theme')->validate($theme, new Assert\Choice(
                choices: array_column(Theme::cases(), 'value'),
                message: 'Choose one of: {{ choices }}.',
            ));
        }
        if ($mon !== null) {
            $validator->atPath('banner.mon')->validate($mon, [
                new Assert\Choice(choices: array_column(Mon::cases(), 'value'), message: 'Choose one of: {{ choices }}.'),
                new Assert\Callback(function (string $value, ExecutionContextInterface $context) use ($user): void {
                    $mon = Mon::tryFrom($value);
                    if ($mon instanceof Mon && ! $this->noboriCollection->has($user, $mon)) {
                        $context->addViolation('You have not earned this mon yet.');
                    }
                }),
            ]);
        }
        if ($pattern !== null) {
            $validator->atPath('banner.pattern')->validate($pattern, [
                new Assert\Choice(choices: array_column(BannerPattern::cases(), 'value'), message: 'Choose one of: {{ choices }}.'),
                new Assert\Callback(function (string $value, ExecutionContextInterface $context) use ($user): void {
                    $pattern = BannerPattern::tryFrom($value);
                    if ($pattern instanceof BannerPattern && ! $this->noboriCollection->has($user, $pattern)) {
                        $context->addViolation('You have not earned this pattern yet.');
                    }
                }),
            ]);
        }
        $violations = $validator->getViolations();
        if ($violations->count() > 0) {
            return $this->validationProblem($violations);
        }

        if ($name !== null) {
            $user->rename($name);
        }
        if ($clan !== null) {
            $user->preferClan(Clan::from($clan));
        }
        if ($cardDisplay !== null) {
            $user->changeSettings($user->getSettings()->withCardDisplay(CardDisplay::from($cardDisplay)));
        }
        if ($theme !== null) {
            $user->changeSettings($user->getSettings()->withTheme(Theme::from($theme)));
        }
        if ($mon !== null) {
            $user->changeBanner($user->getBanner()->withMon(Mon::from($mon)));
        }
        if ($pattern !== null) {
            $user->changeBanner($user->getBanner()->withPattern(BannerPattern::from($pattern)));
        }
        $this->entityManager->flush();

        return $this->index($user);
    }

    /**
     * Deletes the account of the user (AccountDeleter): a human who signs in, not a guest nor a bot.
     */
    #[Route('/api/self', name: 'app_api_self_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $user): Response
    {
        if (! $user->canLinkAccounts()) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'account_required', 'Only an account signed in to with a username is deleted.');
        }

        $this->accountDeleter->delete($user);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/self/games', name: 'app_api_self_games', methods: ['GET'])]
    public function games(#[CurrentUser] UserInterface $user): JsonResponse
    {
        $userId = $user->getUserIdentifier();

        return $this->json($this->userGameViewFactory->createAll(
            $this->gameRepository->findByUser($userId),
            $userId,
        ));
    }

    /**
     * The subscription of the user to the Mercure hub: the hub, their topic, and a token for it and the games they play,
     * in the cookie the hub reads.
     */
    #[Route('/api/self/subscription', name: 'app_api_self_subscription', methods: ['GET'])]
    public function subscription(#[CurrentUser] UserInterface $user): JsonResponse
    {
        [$subscription, $cookie] = $this->subscriptionFactory->create($user->getUserIdentifier());
        $response = $this->json($subscription);
        $response->headers->setCookie($cookie);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Repository\InvitationRepository;
use App\Security\InvitationCode;
use App\Service\Invitation\InvitationIssuer;
use App\View\Model\InvitationBotView;
use App\View\Model\InvitationStatusView;
use App\View\Model\InvitationView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The invitations to a friendly game (InvitationIssuer): an account invites its bot or a friend (`{"kind": "bot" |
 * "friend"}`), then enters the queue with it (`POST /api/queue`, `invitation_id`); the friend enters it with the code
 * (`invitation_code`), the bot with its key. Anyone with the code reads who invites and whether it can be used. Errors
 * are problem details (RFC 9457).
 */
class ApiInvitationsController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly InvitationIssuer $invitationIssuer,
        private readonly InvitationRepository $invitationRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/api/invitations', name: 'app_api_invitations_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $exception) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_json', $exception->getMessage());
        }

        $kind = is_string($body['kind'] ?? null) ? InvitationKind::tryFrom($body['kind']) : null;
        if (! $kind instanceof InvitationKind || array_diff(array_keys($body), ['kind']) !== []) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'invalid_payload', 'The body must only have a "kind": "bot" or "friend".', [
                'violations' => [['property_path' => 'kind', 'message' => 'Whom the invitation is for: "bot" or "friend".']],
            ]);
        }

        if (! $user->canInvite()) {
            return $this->problem(Response::HTTP_FORBIDDEN, 'account_required', 'Only an account signed in to with a username invites.');
        }

        [$invitation, $token] = $this->invitationIssuer->issue($user, $kind);

        return $this->json($this->view($invitation, $token), Response::HTTP_CREATED);
    }

    /**
     * The invitation of the user that has not led to a game yet: its key is not sent again.
     */
    #[Route('/api/self/invitation', name: 'app_api_self_invitation', methods: ['GET'])]
    public function current(#[CurrentUser] User $user): Response
    {
        $invitation = $this->invitationRepository->findUnusedOfHost($user);
        if (! $invitation instanceof Invitation) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'invitation_not_found', 'The user has no invitation waiting.');
        }

        return $this->json($this->view($invitation, null));
    }

    #[Route('/api/self/invitation', name: 'app_api_self_invitation_cancel', methods: ['DELETE'])]
    public function cancel(#[CurrentUser] User $user): Response
    {
        if (! $this->invitationIssuer->cancel($user)) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'invitation_not_found', 'The user has no invitation waiting.');
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/invitations/{code}', name: 'app_api_invitations_show', methods: ['GET'])]
    public function show(string $code): Response
    {
        $invitation = $this->invitationRepository->findOneByCode(InvitationCode::normalize($code));
        if (! $invitation instanceof Invitation) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'invitation_not_found', 'No invitation has this code: it may have been cancelled.');
        }

        $state = match (true) {
            $invitation->isUsed() => 'used',
            $invitation->isExpired($this->clock->now()) => 'expired',
            default => 'pending',
        };

        return $this->json(new InvitationStatusView($invitation->getHost()->getName(), $state), headers: ['Cache-Control' => 'no-store']);
    }

    private function view(Invitation $invitation, ?string $token): InvitationView
    {
        $bot = $invitation->getBot();
        $code = $invitation->getCode();

        return new InvitationView(
            $invitation->getId(),
            $invitation->getKind(),
            $code === null ? null : InvitationCode::format($code),
            $bot instanceof User ? new InvitationBotView($bot->getId(), $bot->getName()) : null,
            $token,
            $invitation->getExpiresAt(),
        );
    }
}

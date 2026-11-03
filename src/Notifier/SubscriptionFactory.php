<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Notification\Topics;
use App\View\Model\SubscriptionView;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;

/**
 * The subscription of a user to the Mercure hub: its public URL, and a token for the topics they may read (see Topics)
 * in the cookie the hub reads (the protocol 1.0 accepts no token in the URL, and a browser cannot send headers with an
 * `EventSource`): HTTP only, sent to the hub only, on the origin of the site. Short-lived, so that a revoked session
 * does not read for long: the client asks for another one once the hub refuses it.
 */
readonly class SubscriptionFactory
{
    public const int LIFETIME = 3600;

    public function __construct(
        private HubInterface $hub,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{SubscriptionView, Cookie}
     */
    public function create(string $userId): array
    {
        $factory = $this->hub->getFactory() ?? throw new \LogicException('The Mercure hub has no token factory.');
        // to the second: the claim, the cookie and the response tell the same time
        $expiresAt = new \DateTimeImmutable('@' . ($this->clock->now()->getTimestamp() + self::LIFETIME));
        // user can subscribe to their topics, and cannot publish
        $subscribe = new Grant([Grant::ACTION_SUBSCRIBE], Topics::subscribableBy($userId));
        $token = $factory->create([$subscribe], ['exp' => $expiresAt, 'sub' => $userId]);

        $cookie = Cookie::create(
            $this->hub->getCookieName(),
            $token,
            $expiresAt,
            path: parse_url($this->hub->getPublicUrl(), \PHP_URL_PATH) ?: '/',
            // secure once the request is (HTTPS)
            secure: null,
            sameSite: Cookie::SAMESITE_STRICT,
        );

        return [
            new SubscriptionView(
                hub: $this->hub->getPublicUrl(),
                topic: Topics::user($userId),
                expiresAt: $expiresAt->format(\DateTimeInterface::ATOM),
            ),
            $cookie,
        ];
    }
}

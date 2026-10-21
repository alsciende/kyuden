<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Notification\Topics;
use App\View\Model\SubscriptionView;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mercure\HubInterface;

/**
 * The subscription of a user to the Mercure hub: its public URL and a token for the topics they may read (see
 * Topics), short-lived so that a revoked session does not read for long. The client asks for another one once the hub
 * refuses it.
 */
readonly class SubscriptionFactory
{
    public const int LIFETIME = 3600;

    public function __construct(
        private HubInterface $hub,
        private ClockInterface $clock,
    ) {
    }

    public function create(string $userId): SubscriptionView
    {
        $factory = $this->hub->getFactory() ?? throw new \LogicException('The Mercure hub has no token factory.');
        // to the second: the claim and the response tell the same time
        $expiresAt = new \DateTimeImmutable('@' . ($this->clock->now()->getTimestamp() + self::LIFETIME));

        return new SubscriptionView(
            hub: $this->hub->getPublicUrl(),
            token: $factory->create(Topics::subscribableBy($userId), [], ['exp' => $expiresAt]),
            topic: Topics::user($userId),
            expiresAt: $expiresAt->format(\DateTimeInterface::ATOM),
        );
    }
}

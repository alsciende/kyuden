<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Notification\NotificationInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Publishes the notifications on the Mercure hub, as private updates: every topic is private (see Topics).
 */
readonly class MercureNotifier implements NotifierInterface
{
    public function __construct(
        private HubInterface $hub,
        private SerializerInterface $serializer,
    ) {
    }

    #[\Override]
    public function notify(NotificationInterface $notification): void
    {
        $update = new Update(
            $notification->getTopics(),
            $this->serializer->serialize($notification->getData(), 'json'),
            private: true,
        );

        $this->hub->publish($update);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Notification\NotificationInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

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
        );

        $this->hub->publish($update);
    }
}

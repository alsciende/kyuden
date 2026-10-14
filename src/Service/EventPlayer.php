<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Message\ActionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

readonly class EventPlayer
{
    public function __construct(
        private SerializerInterface $serializer,
        private MessageBusInterface $bus,
    ) {
    }

    public function playEvent(Event $event): void
    {
        $this->play($event->getName(), $event->getPayload());
    }

    /**
     * @param string $name    class of the message
     * @param string $payload JSON content of the message
     */
    public function play(string $name, string $payload): void
    {
        $message = $this->serializer->deserialize(
            data: $payload,
            type: $name,
            format: 'json',
        );

        if (! $message instanceof ActionInterface) {
            throw new \LogicException("Event payload class {$name} does not implement " . ActionInterface::class);
        }

        $this->bus->dispatch($message);
    }
}

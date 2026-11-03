<?php

declare(strict_types=1);

namespace App\Tests\Service\Bot;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A bus whose first dispatches fail, the others going to the bus it decorates. It records every message dispatched.
 */
final class FailingBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $messages = [];

    public function __construct(
        private readonly MessageBusInterface $bus,
        private int $failures,
    ) {
    }

    #[\Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;
        if ($this->failures > 0) {
            --$this->failures;

            throw new \RuntimeException('Boom');
        }

        return $this->bus->dispatch($message, $stamps);
    }
}

<?php

declare(strict_types=1);

namespace App\State\Manager;

use App\State\Model\StatefulInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @template TEntity of StatefulInterface
 * @template TState of object
 */
abstract readonly class AbstractStateManager
{
    public function __construct(
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Return the class-string of the state object (TState).
     *
     * @return class-string<TState>
     */
    abstract protected function getStateClass(): string;

    /**
     * @param TEntity $object
     *
     * @return TState
     *
     * @throws ExceptionInterface
     */
    public function getState(StatefulInterface $object): mixed
    {
        $serialized = $object->getState();
        $this->logger->debug('State getter', [
            'class' => $object::class,
            'id' => $object->getId(),
            'state' => $serialized,
        ]);

        // @phpstan-ignore return.type
        return $this->serializer->deserialize($serialized, $this->getStateClass(), 'json');
    }

    /**
     * @param TEntity $object
     * @param TState  $state
     *
     * @throws ExceptionInterface
     */
    public function setState(StatefulInterface $object, object $state): void
    {
        $serialized = $this->serializer->serialize($state, 'json');
        $this->logger->debug('State setter', [
            'class' => $object::class,
            'id' => $object->getId(),
            'before' => $object->getState(),
            'after' => $serialized,
        ]);

        $object->setState($serialized);
    }

    /**
     * @param TEntity                 $object
     * @param \Closure(TState): mixed $closure
     *
     * @throws ExceptionInterface
     */
    public function withState(StatefulInterface $object, \Closure $closure): void
    {
        $state = $this->getState($object);
        $closure($state);
        $this->setState($object, $state);
    }
}

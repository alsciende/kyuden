<?php

declare(strict_types=1);

namespace App\Service;

use App\Attribute\AppAction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\String\u;

/**
 * This class configures the Action messages.
 */
class ActionMessageRegistry
{
    /**
     * @var array<string, class-string>
     */
    private array $actionMessages = [];

    /**
     * @var array<class-string, AppAction>
     */
    private array $attributes = [];

    /**
     * @param list<class-string> $classNames
     */
    public function __construct(
        #[Autowire(param: '.app.action_messages')]
        array $classNames,
    ) {
        foreach ($classNames as $className) {
            $reflection = new \ReflectionClass($className);
            $eventName = u($reflection->getShortName())->snake()->toString();
            $this->actionMessages[$eventName] = $className;
            $this->attributes[$className] = ($reflection->getAttributes(AppAction::class)[0] ?? null)?->newInstance() ?? new AppAction();
        }
    }

    public function getMessageClass(string $eventName): string
    {
        return $this->actionMessages[$eventName];
    }

    /**
     * @param class-string $className
     *
     * @return string the type of a message: the snake case short name of its class
     */
    public function getMessageType(string $className): string
    {
        $type = array_search($className, $this->actionMessages, true);

        return \is_string($type) ? $type : throw new \OutOfBoundsException("Not an action message: {$className}");
    }

    /**
     * @return class-string|null
     */
    public function findMessageClass(string $eventName): ?string
    {
        return $this->actionMessages[$eventName] ?? null;
    }

    /**
     * @return array<string, class-string> the messages players can send through the API, by type, sorted by type
     */
    public function getPublicMessageClasses(): array
    {
        $classes = array_filter($this->actionMessages, fn (string $className): bool => $this->getAttribute($className)->public);
        ksort($classes);

        return $classes;
    }

    /**
     * @return list<string> the types of the public messages the web client only offers when a card needs them, sorted
     */
    public function getSpecialMessageTypes(): array
    {
        return array_keys(array_filter($this->getPublicMessageClasses(), fn (string $className): bool => $this->getAttribute($className)->special));
    }

    /**
     * @param class-string $className
     */
    public function getAttribute(string $className): AppAction
    {
        return $this->attributes[$className] ?? throw new \OutOfBoundsException("Not an action message: {$className}");
    }
}

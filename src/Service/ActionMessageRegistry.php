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
     * @return class-string|null
     */
    public function findMessageClass(string $eventName): ?string
    {
        return $this->actionMessages[$eventName] ?? null;
    }

    /**
     * @param class-string $className
     */
    public function getAttribute(string $className): AppAction
    {
        return $this->attributes[$className] ?? throw new \OutOfBoundsException("Not an action message: {$className}");
    }
}

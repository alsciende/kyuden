<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function Symfony\Component\String\u;

/**
 * This class configures the Action messages.
 */
class ActionMessageRegistry
{
    /**
     * @var array<string, string>
     */
    private array $actionMessages = [];

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
        }
    }

    public function getMessageClass(string $eventName): string
    {
        return $this->actionMessages[$eventName];
    }
}

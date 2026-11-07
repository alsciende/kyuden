<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Task\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every task of src/Task carries #[AsMessage] (not a service of the container), and has a handler beside it, registered
 * on task.bus only.
 */
class TaskConventionsTest extends TestCase
{
    /**
     * @return array<class-string, list<\ReflectionClass<object>>> the handlers of each task
     */
    private function tasks(): array
    {
        $tasks = [];
        $handlers = [];
        foreach (new Finder()->files()->in(\dirname(__DIR__, 2) . '/src/Task')->name('*.php') as $file) {
            $class = 'App\\Task\\' . $file->getBasename('.php');
            self::assertTrue(class_exists($class) || interface_exists($class), $class);
            $reflection = new \ReflectionClass($class);
            if ($reflection->implementsInterface(TaskInterface::class) && ! $reflection->isInterface()) {
                $tasks[$class] = [];
            } elseif ($reflection->getAttributes(AsMessageHandler::class) !== []) {
                $handlers[] = $reflection;
            }
        }
        foreach ($handlers as $handler) {
            $type = $handler->getMethod('__invoke')->getParameters()[0]->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            $tasks[$type->getName()][] = $handler;
        }

        return $tasks;
    }

    public function testEveryTaskIsAMessageHandledOnTheTaskBus(): void
    {
        $tasks = $this->tasks();
        self::assertNotEmpty($tasks);
        foreach ($tasks as $task => $handlers) {
            self::assertTrue(is_a($task, TaskInterface::class, true), "{$task} is handled in src/Task but is not a task");
            self::assertNotEmpty(new \ReflectionClass($task)->getAttributes(AsMessage::class), "{$task} lacks #[AsMessage]");
            self::assertCount(1, $handlers, "{$task} needs one handler");
            $attribute = $handlers[0]->getAttributes(AsMessageHandler::class)[0]->newInstance();
            self::assertSame('task.bus', $attribute->bus, "{$handlers[0]->getName()} is not registered on task.bus only");
        }
    }
}

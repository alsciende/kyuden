<?php

declare(strict_types=1);

namespace App;

use App\Attribute\AppAction;
use App\GameLog\GameLog;
use App\GameLog\GameLogEventListener;
use App\Message\PlayerActionInterface;
use App\MessageHandler\PlayerActionHandlerInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(AppAction::class, static function (ChildDefinition $definition): void {
            $definition->addResourceTag('app.action_message');
        });
    }

    public function process(ContainerBuilder $container): void
    {
        $classes = [];
        foreach ($container->findTaggedResourceIds('app.action_message') as $id => $tags) {
            $classes[] = $container->getDefinition($id)->getClass();
        }

        $container->setParameter('.app.action_messages', $classes);

        $container->setAlias('.app.player_action_handlers', (string) ServiceLocatorTagPass::register($container, $this->findPlayerActionHandlers($container)));

        $gameLogListener = $container->getDefinition(GameLogEventListener::class);
        foreach ($this->findLoggedEvents($container) as $eventClass) {
            // before the listeners applying its consequences, which write their own entries
            $gameLogListener->addTag('kernel.event_listener', [
                'event' => $eventClass,
                'priority' => 100,
            ]);
        }
    }

    /**
     * The domain events of src/Event carrying the GameLog attribute (they are not services: their directory is read).
     *
     * @return list<class-string>
     */
    private function findLoggedEvents(ContainerBuilder $container): array
    {
        $directory = $this->getProjectDir() . '/src/Event';
        $container->addResource(new DirectoryResource($directory, '/\\.php$/'));

        $classes = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $class = 'App\\Event\\' . basename($file, '.php');
            if (class_exists($class) && new \ReflectionClass($class)->getAttributes(GameLog::class) !== []) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * The handlers implementing PlayerActionHandlerInterface, by class of the message they handle: the type of the
     * parameter of __invoke(), as Messenger resolves it.
     *
     * @return array<class-string, Reference>
     */
    private function findPlayerActionHandlers(ContainerBuilder $container): array
    {
        $handlers = [];
        foreach (array_keys($container->findTaggedServiceIds('messenger.message_handler')) as $id) {
            $class = $container->getDefinition($id)->getClass();
            if ($class === null || ! is_subclass_of($class, PlayerActionHandlerInterface::class)) {
                continue;
            }

            $type = new \ReflectionMethod($class, '__invoke')->getParameters()[0]->getType();
            $messageClass = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            if ($messageClass === null || ! is_subclass_of($messageClass, PlayerActionInterface::class)) {
                throw new \LogicException(sprintf('The handler %s implements %s but does not handle a single %s.', $class, PlayerActionHandlerInterface::class, PlayerActionInterface::class));
            }

            if (isset($handlers[$messageClass])) {
                throw new \LogicException(sprintf('Several handlers implementing %s handle %s.', PlayerActionHandlerInterface::class, $messageClass));
            }

            $handlers[$messageClass] = new Reference($id);
        }

        return $handlers;
    }
}

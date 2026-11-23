<?php

declare(strict_types=1);

namespace App;

use App\Attribute\AppAction;
use App\CardScript\AbstractCardScript;
use App\Event\TriggeringEventInterface;
use App\GameLog\GameLog;
use App\GameLog\GameLogEventListener;
use App\Message\PlayerActionInterface;
use App\MessageHandler\PlayerActionHandlerInterface;
use App\Service\CardScript\ReactionOfferer;
use App\Trigger\ImminentTriggerInterface;
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

        $subscriptions = $this->findCardScriptSubscriptions($container, TriggeringEventInterface::class);
        $container->setParameter('.app.card_script_subscriptions', $subscriptions);
        $container->setParameter('.app.card_script_interrupt_subscriptions', $this->findCardScriptSubscriptions($container, ImminentTriggerInterface::class));
        $reactionOfferer = $container->getDefinition(ReactionOfferer::class);
        foreach (array_keys($subscriptions) as $eventClass) {
            // after the listeners applying its consequences: the cards read the state once it has resolved
            $reactionOfferer->addTag('kernel.event_listener', [
                'event' => $eventClass,
                'priority' => -100,
            ]);
        }
    }

    /**
     * What the scripts of the cards subscribe to (AbstractCardScript::getSubscribedEvents()), of one kind: the domain
     * events their reactions answer (TriggeringEventInterface), or the imminent conditions their interrupts answer
     * (ImminentTriggerInterface). The method of the script, by code of card, by class of event or condition.
     *
     * @template T of object
     *
     * @param class-string<T> $kind
     *
     * @return array<class-string<T>, array<string, string>>
     */
    private function findCardScriptSubscriptions(ContainerBuilder $container, string $kind): array
    {
        $subscriptions = [];
        foreach (array_keys($container->findTaggedServiceIds(AbstractCardScript::class)) as $id) {
            $class = $container->getDefinition($id)->getClass();
            if ($class === null || ! is_subclass_of($class, AbstractCardScript::class)) {
                continue;
            }

            foreach ($class::getSubscribedEvents() as $eventClass => $method) {
                if (! is_subclass_of($eventClass, TriggeringEventInterface::class) && ! is_subclass_of($eventClass, ImminentTriggerInterface::class)) {
                    throw new \LogicException(sprintf('The script %s subscribes to %s, which implements neither %s nor %s.', $class, $eventClass, TriggeringEventInterface::class, ImminentTriggerInterface::class));
                }

                if (! is_subclass_of($eventClass, $kind)) {
                    continue;
                }

                if (! method_exists($class, $method) || ! new \ReflectionMethod($class, $method)->isPublic()) {
                    throw new \LogicException(sprintf('The script %s subscribes to %s with %s(), which is not a public method of it.', $class, $eventClass, $method));
                }

                $subscriptions[$eventClass][$class::getCode()] = $method;
            }
        }

        ksort($subscriptions);

        return $subscriptions;
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

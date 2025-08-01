<?php

declare(strict_types=1);

namespace App\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class MakeWorkflowListener extends AbstractMaker
{
    private string $listenerClass;
    private string $eventClass;
    private string $attributeClass;
    /**
     * @var array<string, string>
     */
    private array $attributeParameters = [];

    /**
     * @var array<string, WorkflowInterface>
     */
    private array $workflows = [];

    /**
     * @param iterable<WorkflowInterface> $workflows
     */
    public function __construct(
        #[AutowireIterator('workflow')]
        iterable $workflows,
    ) {
        foreach ($workflows as $workflow) {
            $this->workflows[$workflow->getName()] = $workflow;
        }
    }

    public static function getCommandName(): string
    {
        return 'make:workflow-listener';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        /** @var string $workflowName */
        $workflowName = $io->choice('Which workflow do you want to listen to?', array_keys($this->workflows));
        /** @var WorkflowInterface $workflow */
        $workflow = $this->workflows[$workflowName];

        /** @var string $event */
        $event = $io->choice('Which event do you want to listen to?', ['guard', 'leave', 'transition', 'enter', 'entered']);
        $this->eventClass = 'Symfony\\Component\\Workflow\\Event\\' . Str::asClassName($event) . 'Event';
        $this->attributeClass = 'Symfony\\Component\\Workflow\\Attribute\\As' . Str::asClassName($event) . 'Listener';

        if (in_array($event, ['guard', 'transition', 'completed', 'announce'], true)) {
            /** @var string $transitionName */
            $transitionName = $io->choice(
                'Which transition do you want to listen to?',
                array_map(
                    fn (Transition $transition): string => $transition->getName(),
                    $workflow->getDefinition()->getTransitions()
                )
            );
            $this->listenerClass = 'App\\Workflow\\' . Str::asClassName($workflowName) . '\\' . Str::asClassName($transitionName) . Str::asClassName($event) . 'Listener';
            $this->attributeParameters = [
                'workflow' => $workflowName,
                'transition' => $transitionName,
            ];
        } else {
            /** @var string $placeName */
            $placeName = $io->choice(
                'Which place do you want to listen to?',
                $workflow->getDefinition()->getPlaces()
            );
            $this->listenerClass = 'App\\Workflow\\' . Str::asClassName($workflowName) . '\\' . Str::asClassName($placeName) . Str::asClassName($event) . 'Listener';
            $this->attributeParameters = [
                'workflow' => $workflowName,
                'place' => $placeName,
            ];
        }

        parent::interact($input, $io, $command);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $parameters = [];
        foreach ($this->attributeParameters as $parameter => $value) {
            $parameters[] = sprintf("%s: '%s'", $parameter, $value);
        }

        $generator->generateClass(
            $this->listenerClass,
            'templates/maker/WorkflowListener.tpl.php',
            [
                'listener_namespace' => Str::getNamespace($this->listenerClass),
                'listener_name' => Str::getShortClassName($this->listenerClass),
                'event_class' => $this->eventClass,
                'event_name' => Str::getShortClassName($this->eventClass),
                'attribute_class' => $this->attributeClass,
                'attribute_name' => Str::getShortClassName($this->attributeClass),
                'attribute_parameters' => implode(', ', $parameters),
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }
}

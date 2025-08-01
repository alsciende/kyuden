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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class MakeAction extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:app:action';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('action-class', InputArgument::OPTIONAL, \sprintf('Choose a name for your action class (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        /** @var string $actionClass */
        $actionClass = $input->getArgument('action-class');
        if (! $actionClass) {
            $actionClass = Str::asClassName(Str::getRandomTerm());
        }

        $generator->generateClass(
            'App\\Message\\' . $actionClass,
            'templates/maker/Action.tpl.php',
            [
                'action_class' => $actionClass,
            ]
        );

        $generator->generateClass(
            'App\\MessageHandler\\' . $actionClass . 'Handler',
            'templates/maker/ActionHandler.tpl.php',
            [
                'action_class_name' => $actionClass,
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }
}

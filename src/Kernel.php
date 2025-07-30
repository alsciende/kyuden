<?php

declare(strict_types=1);

namespace App;

use App\Attribute\AppAction;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    }
}

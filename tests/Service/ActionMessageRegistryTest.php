<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Message\CreateGame;
use App\Service\ActionMessageRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ActionMessageRegistryTest extends KernelTestCase
{
    public function testGetMessageClass(): void
    {
        $container = static::getContainer();
        /** @var ActionMessageRegistry $service */
        $service = $container->get(ActionMessageRegistry::class);
        $this->assertEquals(CreateGame::class, $service->getMessageClass('create_game'));
    }
}

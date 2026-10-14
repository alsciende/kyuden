<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Message\CreateGame;
use App\Message\PassOpportunity;
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

    public function testExposesTheAttributeOfTheMessages(): void
    {
        $service = self::getContainer()->get(ActionMessageRegistry::class);
        self::assertInstanceOf(ActionMessageRegistry::class, $service);

        self::assertNull($service->findMessageClass('unknown'));
        self::assertFalse($service->getAttribute(CreateGame::class)->public);
        self::assertTrue($service->getAttribute(PassOpportunity::class)->public);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ConsoleControllerTest extends AbstractKernelTestCase
{
    public function testAnyoneLoadsTheConsoleWithTheUrlOfTheMercureHub(): void
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', '/console');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('id="command"', (string) $response->getContent());
        self::assertStringContainsString('data-mercure-url="', (string) $response->getContent());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiActionsControllerTest extends AbstractKernelTestCase
{
    public function testAnyoneReadsTheSchemasOfTheActionMessages(): void
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', '/api/actions');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertTrue($response->headers->getCacheControlDirective('public'));
        $actions = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($actions);
        self::assertArrayHasKey('pass_opportunity', $actions);
        self::assertArrayNotHasKey('create_game', $actions);
        self::assertIsArray($actions['bid_honor']);
        self::assertSame('bid_honor', $actions['bid_honor']['title']);
        self::assertSame(['bid'], $actions['bid_honor']['required']);
    }
}

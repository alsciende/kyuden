<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\DataFixtures\UserFixtures;
use App\Tests\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Calls the MCP server as an agent does: HTTP transport, authenticated with the API key of a bot.
 */
abstract class AbstractMcpTestCase extends AbstractKernelTestCase
{
    private ?string $sessionId = null;

    /**
     * @param array<string, mixed> $message JSON-RPC message, without its version
     * @param string               $user    a user of the fixtures, or an API key (`l5r_…`)
     */
    protected function post(array $message, string $user = 'akodo-bot'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('POST', '/api/mcp', server: array_filter([
            'HTTP_AUTHORIZATION' => 'Bearer ' . (UserFixtures::TOKENS[$user] ?? $user),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $this->sessionId,
        ]), content: json_encode(['jsonrpc' => '2.0', ...$message], \JSON_THROW_ON_ERROR));

        return $client->getResponse();
    }

    /**
     * Sends a request of the session (opened on the first request) and returns its response.
     *
     * @param array<string, mixed> $params
     *
     * @return array<mixed>
     */
    protected function request(string $method, array $params = [], string $user = 'akodo-bot'): array
    {
        if ($this->sessionId === null) {
            $response = $this->post(['id' => 0, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1'],
            ]], $user);
            self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
            $this->sessionId = $response->headers->get('Mcp-Session-Id');
            $this->post(['method' => 'notifications/initialized'], $user);
        }

        $response = $this->post(['id' => 1, 'method' => $method, 'params' => (object) $params], $user);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    /**
     * Calls a tool and returns its result.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<mixed>
     */
    protected function callTool(string $name, array $arguments, string $user = 'akodo-bot'): array
    {
        $response = $this->request('tools/call', ['name' => $name, 'arguments' => $arguments], $user);
        self::assertIsArray($response['result'] ?? null, json_encode($response, \JSON_THROW_ON_ERROR));

        return $response['result'];
    }
}

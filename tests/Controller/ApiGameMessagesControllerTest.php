<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiGameMessagesControllerTest extends AbstractKernelTestCase
{
    private function scenarioAtPlayCardsFromProvinces(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->activePlayer('john')
            ->persist();
    }

    /**
     * @param array<mixed>|string   $body
     * @param array<string, string> $headers HTTP headers of the request, by their server name (HTTP_IF_MATCH)
     */
    private function post(string $gameId, array|string $body, ?string $user = 'john', array $headers = []): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            'POST',
            "/api/games/{$gameId}/messages",
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
                ...$headers,
            ]),
            content: is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        return $client->getResponse();
    }

    private function assertProblem(Response $response, int $status, string $type): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $problem = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame($type, $problem['type']);
    }

    /**
     * @return list<mixed> the property paths of the violations of the problem
     */
    private function violations(Response $response, int $status, string $type): array
    {
        $this->assertProblem($response, $status, $type);
        $problem = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertIsArray($problem['violations']);

        return array_column($problem['violations'], 'property_path');
    }

    public function testThePlayerSendsAMessageOnTheirOwnBehalf(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity']);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->isPassed());
    }

    public function testTheETagIsTheVersionOfTheGameOnceTheMessageIsHandled(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity']);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('"1"', $response->getEtag());
    }

    public function testAMessageSentForTheCurrentVersionIsHandled(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity'], headers: ['HTTP_IF_MATCH' => '"0"']);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('"1"', $response->getEtag());
    }

    public function testAMessageSentForAPreviousVersionIsRejected(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();
        $loseHonor = ['type' => 'lose_honor', 'payload' => ['target_player_id' => $scenario->player('john')->getId(), 'honor_loss' => 1]];
        $this->post($scenario->game()->getId(), $loseHonor, 'jane');

        $response = $this->post($scenario->game()->getId(), $loseHonor, headers: ['HTTP_IF_MATCH' => '"0"']);

        $this->assertProblem($response, Response::HTTP_PRECONDITION_FAILED, 'stale_version');
        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testIfMatchMustBeAnETagOfTheGame(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity'], headers: ['HTTP_IF_MATCH' => 'W/"0"']);

        $this->assertProblem($response, Response::HTTP_BAD_REQUEST, 'invalid_if_match');
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->isPassed());
    }

    public function testAnEffectMessageAppliesToItsTargetPlayer(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), [
            'type' => 'lose_honor',
            'payload' => ['target_player_id' => $scenario->player('jane')->getId(), 'honor_loss' => 2],
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(8, $this->playerStateManager->getState($scenario->player('jane'))->getHonor());
    }

    public function testThePayloadCannotHoldTheGameOrThePlayer(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();
        $gameId = $scenario->game()->getId();

        $otherPlayer = $this->post($gameId, ['type' => 'pass_opportunity', 'payload' => ['player_id' => $scenario->player('john')->getId()]], 'jane');
        $sender = $this->post($gameId, ['type' => 'pass_opportunity', 'payload' => ['player_id' => $scenario->player('john')->getId()]]);
        $game = $this->post($gameId, ['type' => 'pass_opportunity', 'payload' => ['game_id' => $gameId]]);

        self::assertSame(['player_id'], $this->violations($otherPlayer, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertSame(['player_id'], $this->violations($sender, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertSame(['game_id'], $this->violations($game, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->isPassed());
    }

    public function testAUserWhoIsNotAPlayerOfTheGameCannotSendMessages(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jack')
            ->persist();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity'], 'jane');

        $this->assertProblem($response, Response::HTTP_FORBIDDEN, 'not_a_player');
    }

    public function testAnAnonymousUserCannotSendMessages(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity'], null);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testTheGameMustExist(): void
    {
        $response = $this->post('unknown', ['type' => 'pass_opportunity']);

        $this->assertProblem($response, Response::HTTP_NOT_FOUND, 'game_not_found');
    }

    public function testTheTypeMustBeAPublicActionMessage(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->assertProblem($this->post($scenario->game()->getId(), ['type' => 'unknown']), Response::HTTP_BAD_REQUEST, 'bad_request');
        $this->assertProblem($this->post($scenario->game()->getId(), ['type' => 'create_game']), Response::HTTP_BAD_REQUEST, 'bad_request');
    }

    public function testTheBodyMustBeValid(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->assertProblem($this->post($scenario->game()->getId(), '{'), Response::HTTP_BAD_REQUEST, 'invalid_json');
        $this->assertProblem($this->post($scenario->game()->getId(), ['payload' => []]), Response::HTTP_BAD_REQUEST, 'invalid_body');
    }

    public function testThePayloadMustMatchTheMessage(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();
        $gameId = $scenario->game()->getId();
        $janeId = $scenario->player('jane')->getId();

        $missing = $this->post($gameId, ['type' => 'lose_honor', 'payload' => ['target_player_id' => $janeId]]);
        $wrongType = $this->post($gameId, ['type' => 'lose_honor', 'payload' => ['target_player_id' => $janeId, 'honor_loss' => 'two']]);
        $extra = $this->post($gameId, ['type' => 'lose_honor', 'payload' => ['target_player_id' => $janeId, 'honor_loss' => 2, 'unknown' => 1]]);

        self::assertSame(['honor_loss'], $this->violations($missing, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertSame(['honor_loss'], $this->violations($wrongType, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertSame(['unknown'], $this->violations($extra, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
        self::assertSame(10, $this->playerStateManager->getState($scenario->player('jane'))->getHonor());
    }

    public function testAnActionForbiddenByTheRulesIsAConflict(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $response = $this->post($scenario->game()->getId(), ['type' => 'pass_opportunity'], 'jane');

        $this->assertProblem($response, Response::HTTP_CONFLICT, 'not_active_player');
    }

    public function testAPayloadReferringToNothingInTheGameIsUnprocessable(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $unknownCard = $this->post($scenario->game()->getId(), ['type' => 'bow_card', 'payload' => ['card_id' => 'unknown']]);
        $negative = $this->post($scenario->game()->getId(), [
            'type' => 'lose_honor',
            'payload' => ['target_player_id' => $scenario->player('jane')->getId(), 'honor_loss' => -1],
        ]);

        $this->assertProblem($unknownCard, Response::HTTP_UNPROCESSABLE_ENTITY, 'card_not_found');
        self::assertSame(['honor_loss'], $this->violations($negative, Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed'));
    }
}

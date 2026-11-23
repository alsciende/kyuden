<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Service\CardScript\CardScriptRegistry;
use App\Service\ControlService;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\CardState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\ProvinceBreaking;

/**
 * The cards interrupting a province broken (doc/interrupts.md, 3), the province still unbroken.
 */
class ProvinceBreakingInterruptsTest extends AbstractKernelTestCase
{
    private const array ALIASES = ['war', 'forum', 'weakpoint', 'kabe', 'watchtower', 'peace'];

    public function testEveryCardInterruptingAProvinceBrokenSubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(ProvinceBreaking::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        // not the cards reading the rings claimed on a province (Enlightenment)
        self::assertEqualsCanonicalizing([
            '01-restoration-of-balance', '01-the-art-of-peace', '01-the-art-of-war', '03-miwaku-kabe',
            '04-feast-or-famine', '04-public-forum', '05-before-the-throne', '09-demonstrating-excellence',
            '10-upholding-authority', '11-abandoning-honor', '12-seeking-the-truth', '13-appealing-to-the-fortunes',
            '23-strategic-weakpoint', '36-watchtower-of-sun-s-shadow', '37-cycle-of-vengeance', '37-the-way-of-peace',
        ], $codes);
    }

    private function scenarioWith(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('37-the-way-of-peace', as: 'peace', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-the-art-of-war', as: 'war', orientation: Orientation::FaceUp)
                ->province('04-public-forum', as: 'forum', dynasty: '23-strategic-weakpoint', dynastyAs: 'weakpoint', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', as: 'walled', dynasty: '36-watchtower-of-sun-s-shadow', dynastyAs: 'watchtower', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', as: 'other', dynasty: '03-miwaku-kabe', dynastyAs: 'kabe', dynastyFaceup: true, orientation: Orientation::FaceUp))
            ->persist();
    }

    /**
     * The cards offered to each player, by alias and stage, for the province broken by john.
     *
     * @return array<string, list<string>> the aliases, by `player:stage`
     */
    private function offers(Scenario $scenario, string $alias): array
    {
        $province = $scenario->card($alias);
        \assert($province instanceof ProvinceCard);
        $controller = self::service(ControlService::class)->getController($province);
        if (! self::service(InterruptOfferer::class)->open($scenario->game(), [new ProvinceBreaking($province, $controller, $scenario->player('john'))])) {
            return [];
        }

        $window = self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
        $offers = [];
        foreach ($window->stages ?? [] as $stage) {
            foreach ($stage->cardIds as $playerId => $cardIds) {
                $aliases = array_values(array_filter(
                    self::ALIASES,
                    fn (string $candidate): bool => in_array($scenario->card($candidate)->getId(), $cardIds, true),
                ));
                if ($aliases !== []) {
                    $player = $playerId === $scenario->player('john')->getId() ? 'john' : 'jane';
                    $offers["{$player}:{$stage->stage->value}"] = $aliases;
                }
            }
        }

        ksort($offers);

        return $offers;
    }

    public function testThisProvinceIsBroken(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:standard' => ['war']], $this->offers($scenario, 'war'));
        self::assertSame(['john:standard' => ['peace']], $this->offers($scenario, 'peace'));
    }

    public function testThisProvinceAndThisHoldingsProvinceWouldBeBroken(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:would' => ['forum', 'weakpoint']], $this->offers($scenario, 'forum'));
    }

    public function testPublicForumWithAnHonorTokenOnIt(): void
    {
        $scenario = $this->scenarioWith();
        self::service(CardStateManager::class)->withState($scenario->card('forum'), function (CardState $state): CardState {
            $state->setHonorTokens(1);

            return $state;
        });

        self::assertSame(['jane:would' => ['weakpoint']], $this->offers($scenario, 'forum'));
    }

    public function testThisHoldingsProvinceIsBroken(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:forced' => ['watchtower']], $this->offers($scenario, 'walled'));
        self::assertSame(['jane:standard' => ['kabe']], $this->offers($scenario, 'other'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\ConflictDeclaredEvent;
use App\Message\DeclareConflict;
use App\MessageHandler\DeclareConflictHandler;
use App\Service\CardScript\CardScriptRegistry;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\ReactionWindow;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\AttackedProvinceRevealedCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;

/**
 * The cards reacting to a conflict declared ("After a conflict is declared against this province", "After this
 * character is declared as an attacker"…): the declaration itself (ConflictDeclaredEvent), then the declaration
 * complete, once the attacked province is revealed (ConflictDeclarationCompletedEvent).
 */
class ConflictDeclaredReactionsTest extends AbstractKernelTestCase
{
    /**
     * The cards reacting before the attacked province is revealed.
     */
    private const array DECLARED_CODES = ['01-enlightened-warrior', '28-the-crashing-wave'];

    /**
     * The other cards of the Stronghold format, the forced reactions included (the five temples of Clan War, Marauding
     * Oni), but Shadowed Alleyway (the rings claimed on the provinces, of the Enlightenment format).
     */
    private const array COMPLETED_CODES = [
        // the forced reactions (ForcedReactionsTest)
        '10-marauding-oni', '28-temple-of-daikoku', '28-temple-of-jikoju', '28-temple-of-osano-wo', '28-temple-of-shinsei-s-wisdom', '28-temple-of-the-thunders',
        '01-endless-plains', '01-mantra-of-fire', '09-ancient-master', '38-ki-alignment', '01-secret-cache', '04-mantra-of-water', '04-war-dog-master',
        '01-spyglass', '42-daikyu',
        '09-hiruma-outpost', '09-sacred-sanctuary', '14-mantra-of-earth', '14-tengu-sensei', '16-midnight-revels',
        '22-fortified-assembly', '22-issue-a-challenge', '23-shiro-kitsuki', '24-mantra-of-air', '25-fuchi-mura',
        '26-shinjo-yasamura', '28-battle-aspirant', '30-never-yield', '31-mantra-of-void', '32-under-siege',
        '34-shiba-pureheart', '41-a-war-on-two-fronts', '41-golden-plains', '42-moto-beastmaster', '42-third-tower',
    ];

    /**
     * @param list<string> $aliases
     *
     * @return list<string> the cards offered to the player, by alias, among those given
     */
    private function offered(Scenario $scenario, string $player, array $aliases): array
    {
        $cardIds = array_merge(...array_map(
            fn (ReactionWindow $window): array => $window->cardIds,
            self::service(PlayerStateManager::class)->getState($scenario->player($player))->getReactionWindows(),
        ));

        return array_values(array_filter($aliases, fn (string $alias): bool => in_array($scenario->card($alias)->getId(), $cardIds, true)));
    }

    /**
     * @param class-string $eventClass
     *
     * @return list<string>
     */
    private function subscribers(string $eventClass): array
    {
        $registry = self::service(CardScriptRegistry::class);

        return array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists($eventClass, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));
    }

    public function testEveryCardReactingToAConflictDeclaredSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(self::DECLARED_CODES, $this->subscribers(ConflictDeclaredEvent::class));
        self::assertEqualsCanonicalizing(self::COMPLETED_CODES, $this->subscribers(ConflictDeclarationCompletedEvent::class));
    }

    public function testTheCardsReactingBeforeTheAttackedProvinceIsRevealed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['warrior' => '01-enlightened-warrior'])
                ->hand(['wave' => '28-the-crashing-wave'])
                ->province('01-entrenched-position', as: 'province'))
            ->activePlayer('john')
            ->ring(Element::Fire, fate: 2)
            ->persist();

        self::service(DeclareConflictHandler::class)(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Fire,
            ConflictType::Military,
            $scenario->card('province')->getId(),
            [$scenario->card('attacker')->getId()],
        ));

        self::assertSame(['warrior', 'wave'], $this->offered($scenario, 'jane', ['warrior', 'wave']));
    }

    public function testNothingReactsBeforeTheRevealToARingWithoutFateAndAProvinceFaceUp(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['attacker' => '01-togashi-yokuni']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['warrior' => '01-enlightened-warrior'])
                ->hand(['wave' => '28-the-crashing-wave'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->activePlayer('john')
            ->persist();

        self::service(DeclareConflictHandler::class)(new DeclareConflict(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            Element::Fire,
            ConflictType::Military,
            $scenario->card('province')->getId(),
            [$scenario->card('attacker')->getId()],
        ));

        self::assertSame([], $this->offered($scenario, 'jane', ['warrior', 'wave']));
    }

    public function testTheCardsReactingOnceTheAttackedProvinceIsRevealed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_conflict_declaration')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['dog_master' => '04-war-dog-master'])
                ->inPlay(['tengu' => '14-tengu-sensei'])
                ->inPlay(['at_home' => '04-war-dog-master'])
                ->hand(['never_yield' => '30-never-yield', 'challenge' => '22-issue-a-challenge', 'johns_mantra' => '01-mantra-of-fire']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('23-shiro-kitsuki', as: 'shiro')
                ->hand(['fire_mantra' => '01-mantra-of-fire', 'water_mantra' => '04-mantra-of-water'])
                ->province('01-secret-cache', as: 'cache', orientation: Orientation::FaceUp)
                ->province('01-endless-plains', as: 'plains', orientation: Orientation::FaceUp))
            ->conflict('john', 'cache', attackers: ['dog_master', 'tengu'], ring: Element::Fire)
            ->withGameState(function (GameState $state, Scenario $scenario): void {
                $state->currentConflict()->evadedIds = [$scenario->card('shiro')->getId()];
            })
            ->persist();
        $aliases = ['dog_master', 'tengu', 'at_home', 'never_yield', 'challenge', 'johns_mantra', 'shiro', 'fire_mantra', 'water_mantra', 'cache', 'plains'];

        self::service(AttackedProvinceRevealedCompletedListener::class)(new CompletedEvent($scenario->game(), new Marking()));

        // Issue a Challenge: two attackers; the Mantra of Fire: of the opponent of the attacking player
        self::assertSame(['dog_master', 'tengu', 'never_yield'], $this->offered($scenario, 'john', $aliases));
        // not the province that is not attacked, nor the mantra of another element
        self::assertSame(['shiro', 'fire_mantra', 'cache'], $this->offered($scenario, 'jane', $aliases));
    }
}

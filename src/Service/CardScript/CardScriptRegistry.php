<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\CardScript\AbstractCardScript;
use App\Enum\AbilityTiming;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The scripts of the cards (AbstractCardScript), by code, instantiated when first needed.
 */
final readonly class CardScriptRegistry
{
    /**
     * @param ServiceLocator<AbstractCardScript> $scripts
     */
    public function __construct(
        #[AutowireLocator(AbstractCardScript::class, defaultIndexMethod: 'getCode')]
        private ServiceLocator $scripts,
    ) {
    }

    /**
     * The script of a card by its code, null when its text needs none.
     */
    public function find(string $code): ?AbstractCardScript
    {
        return $this->scripts->has($code) ? $this->scripts->get($code) : null;
    }

    /**
     * The timings of the triggered abilities printed on a card, by its code (none without a script).
     *
     * @return list<AbilityTiming>
     */
    public function getAbilityTimings(string $code): array
    {
        return $this->find($code)?->getAbilityTimings() ?? [];
    }

    public function hasAbilityTiming(string $code, AbilityTiming $timing): bool
    {
        return in_array($timing, $this->getAbilityTimings($code), true);
    }

    /**
     * The triggered abilities of a card, by its code, are granted to the card it is attached to.
     */
    public function isAbilityGrantedToAttached(string $code): bool
    {
        return $this->find($code)?->isAbilityGrantedToAttached() ?? false;
    }

    /**
     * @return list<string> the codes of the cards that have a script
     */
    public function getCodes(): array
    {
        return array_keys($this->scripts->getProvidedServices());
    }
}

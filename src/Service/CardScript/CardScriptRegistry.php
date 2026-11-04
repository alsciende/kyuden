<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\CardScript\AbstractCardScript;
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
     * @return list<string> the codes of the cards that have a script
     */
    public function getCodes(): array
    {
        return array_keys($this->scripts->getProvidedServices());
    }
}

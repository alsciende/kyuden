<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotEnoughHonorTokensException extends RulesException
{
    public function __construct(int $amount, int $honorTokens)
    {
        parent::__construct(sprintf('Cannot remove %d honor tokens: there are only %d on the card.', $amount, $honorTokens));
    }
}

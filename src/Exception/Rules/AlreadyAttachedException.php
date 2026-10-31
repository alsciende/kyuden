<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class AlreadyAttachedException extends RulesException
{
    public function __construct(PhysicalCard $attachment, PhysicalCard $target)
    {
        parent::__construct(sprintf('Attachment [%s] is already attached to card [%s].', $attachment->getId(), $target->getId()));
    }
}

<?php

declare(strict_types=1);

namespace App\Attribute;

/**
 * Describes a field of an action message for the clients of the API, in the JSON schema of the message
 * (MessageSchemaFactory). The message itself is described by its class doc comment.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
readonly class Description
{
    public function __construct(
        public string $description,
    ) {
    }
}

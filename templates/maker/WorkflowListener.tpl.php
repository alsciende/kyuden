<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $listener_namespace ?>;

use <?= $attribute_class ?>;
use <?= $event_class ?>;

#[<?= $attribute_name ?>(<?= $attribute_parameters ?>)]
readonly class <?= $listener_name ?>
{
    public function __invoke(<?= $event_name ?> $event): void
    {
    }
}

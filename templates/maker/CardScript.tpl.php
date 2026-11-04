<?= "<?php\n" ?>

declare(strict_types=1);

namespace App\CardScript;

<?php if ($message_classes !== [] || $timings !== []): ?>
<?php if ($message_classes !== []): ?>
use App\Entity\Game;
use App\Entity\PhysicalCard;
<?php endif; ?>
<?php if ($timings !== []): ?>
use App\Enum\AbilityTiming;
<?php endif; ?>
<?php foreach ($message_classes as $message_class): ?>
use <?= $message_class ?>;
<?php endforeach; ?>

<?php endif; ?>
/**
 * <?= $card_name ?>.
 */
final readonly class <?= $class_name ?> extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '<?= $code ?>';
    }
<?php if ($timings !== []): ?>

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [<?= implode(', ', array_map(fn (string $case): string => 'AbilityTiming::' . $case, $timings)) ?>];
    }
<?php endif; ?>
<?php if ($granted_to_attached): ?>

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
<?php endif; ?>
<?php if ($message_classes !== []): ?>

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [<?= implode(', ', array_map(fn (string $name): string => $name . '::class', $message_names)) ?>];
    }
<?php endif; ?>
}

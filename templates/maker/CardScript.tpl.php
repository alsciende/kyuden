<?= "<?php\n" ?>

declare(strict_types=1);

namespace App\CardScript;

<?php if ($message_classes !== []): ?>
use App\Entity\Game;
use App\Entity\PhysicalCard;
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
<?php if ($message_classes !== []): ?>

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [<?= implode(', ', array_map(fn (string $name): string => $name . '::class', $message_names)) ?>];
    }
<?php endif; ?>
}

<?php

declare(strict_types=1);

namespace App\Maker;

use App\Enum\AbilityTiming;
use App\Repository\LogicalCardRepository;
use App\Service\ActionMessageRegistry;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function Symfony\Component\String\u;

/**
 * The script of a card (AbstractCardScript), from its code, with the timings of its triggered abilities, whether they are
 * granted to the card it is attached to, and the special actions its text needs, if any.
 */
final class MakeCardScript extends AbstractMaker
{
    public function __construct(
        private readonly LogicalCardRepository $logicalCardRepository,
        private readonly ActionMessageRegistry $actionMessageRegistry,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:app:card-script';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates the script of a card';
    }

    /**
     * The class of the script of a card, named after its code: KakitaKaezin01 for 01-kakita-kaezin.
     */
    public static function getClassName(string $code): string
    {
        [$pack, $name] = explode('-', $code, 2) + [1 => ''];

        return 'App\\CardScript\\' . u($name)->camel()->title()->toString() . $pack;
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('code', InputArgument::REQUIRED, 'The code of the card (e.g. <fg=yellow>01-kakita-kaezin</>)')
            ->addOption('timing', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The timing of a triggered ability printed on it (<fg=yellow>action</>, <fg=yellow>interrupt</> or <fg=yellow>reaction</>)', [])
            ->addOption('granted-to-attached', null, InputOption::VALUE_NONE, 'Its triggered abilities are granted to the card it is attached to')
            ->addOption('special-action', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A special action its text needs (e.g. <fg=yellow>initiate_duel</>)', [])
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        /** @var string $code */
        $code = $input->getArgument('code');
        $card = $this->logicalCardRepository->find($code) ?? throw new RuntimeCommandException("Unknown card: {$code}");

        /** @var list<string> $timingValues */
        $timingValues = $input->getOption('timing');
        $timings = [];
        foreach ($timingValues as $value) {
            $timing = AbilityTiming::tryFrom($value) ?? throw new RuntimeCommandException("Not a timing: {$value}");
            $timings[$timing->value] = $timing;
        }

        // in the order of the enum
        $timings = array_values(array_filter(AbilityTiming::cases(), static fn (AbilityTiming $timing): bool => isset($timings[$timing->value])));

        /** @var list<string> $types */
        $types = $input->getOption('special-action');
        $specialTypes = $this->actionMessageRegistry->getSpecialMessageTypes();
        $messageClasses = [];
        foreach ($types as $type) {
            if (! in_array($type, $specialTypes, true)) {
                throw new RuntimeCommandException("Not a special action: {$type}");
            }

            $messageClasses[] = $this->actionMessageRegistry->getMessageClass($type);
        }

        sort($messageClasses);

        $generator->generateClass(
            self::getClassName($code),
            'templates/maker/CardScript.tpl.php',
            [
                'code' => $code,
                'card_name' => $card->getName(),
                'timings' => array_map(static fn (AbilityTiming $timing): string => $timing->name, $timings),
                'granted_to_attached' => (bool) $input->getOption('granted-to-attached'),
                'message_classes' => $messageClasses,
                'message_names' => array_map(Str::getShortClassName(...), $messageClasses),
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }
}

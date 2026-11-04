<?php

declare(strict_types=1);

namespace App\Maker;

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
 * The script of a card (AbstractCardScript), from its code, with the special actions its text needs, if any.
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
                'message_classes' => $messageClasses,
                'message_names' => array_map(Str::getShortClassName(...), $messageClasses),
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }
}

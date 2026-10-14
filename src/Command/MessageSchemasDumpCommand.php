<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Api\MessageSchemaFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes the JSON schemas of the public action messages in schemas/messages, one file per type. The files are the
 * snapshot of the contract of the API: a test fails when they differ from the schemas built from the messages, so
 * that every change of the contract shows in the diff.
 */
#[AsCommand(
    name: 'app:message-schemas:dump',
    description: 'Write the JSON schemas of the public action messages',
)]
readonly class MessageSchemasDumpCommand
{
    public const string DIRECTORY = 'schemas/messages';

    public function __construct(
        private MessageSchemaFactory $messageSchemaFactory,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = "{$this->projectDir}/" . self::DIRECTORY;
        if (! is_dir($directory) && ! mkdir($directory, recursive: true)) {
            throw new \RuntimeException("Cannot create {$directory}.");
        }

        // schemas of messages that no longer exist or are no longer public
        array_map(unlink(...), glob("{$directory}/*.json") ?: []);
        $schemas = $this->messageSchemaFactory->createAll();
        foreach ($schemas as $type => $schema) {
            file_put_contents("{$directory}/{$type}.json", MessageSchemaFactory::encode($schema));
        }

        $io->success(sprintf('%d schemas written in %s.', count($schemas), self::DIRECTORY));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ActionRecordPlayer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:record:play',
    description: 'Add a short description for your command',
)]
readonly class RecordPlayCommand
{
    public function __construct(
        private ActionRecordPlayer $player,
    ) {
    }

    /**
     * @param mixed $answer
     * @return class-string
     */
    private function isClassExists($answer): string
    {
        if (! is_string($answer)) {
            throw new \RuntimeException('Expecting a string, got ' . gettype($answer));
        }

        if (class_exists($answer) === false) {
            throw new \RuntimeException('Class does not exist');
        }

        return $answer;
    }

    /**
     * @param mixed $answer
     * @return string
     */
    private function isValidJson($answer): string
    {
        if (! is_string($answer)) {
            throw new \RuntimeException('Expecting a string, got ' . gettype($answer));
        }

        if (json_validate($answer) === false) {
            throw new \RuntimeException('JSON is invalid');
        }

        return $answer;
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nameQuestion = new Question('Class of message');
        $nameQuestion->setValidator($this->isClassExists(...));
        /** @var string $name */
        $name = $io->askQuestion($nameQuestion);
        $payloadQuestion = new Question('Payload of message');
        $payloadQuestion->setValidator($this->isValidJson(...));
        /** @var string $payload */
        $payload = $io->askQuestion($payloadQuestion);
        $io->title("Playing message {$name} with payload {$payload}");
        $this->player->play($name, $payload);
        $io->success('Message played successfully.');

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Repository\CandidateRepository;
use App\Service\GameLauncher\GameLauncherInterface;
use App\Service\MatchMaking\FifoMatchMakingService;
use PHPUnit\Framework\TestCase;

class MatchMakingServiceTest extends TestCase
{
    public function testFindMatches(): void
    {
        $repo = $this->createStub(CandidateRepository::class);
        $repo->method('findAll')->willReturn([
            new Candidate()->setDeck(new Deck('1'))->setUserId('jack'),
            new Candidate()->setDeck(new Deck('2'))->setUserId('jane'),
            new Candidate()->setDeck(new Deck('3'))->setUserId('jill'),
        ]);

        $launcher = $this->createMock(GameLauncherInterface::class);
        $launcher->expects($this->once())
            ->method('createGame')
            ->with($this->containsOnlyInstancesOf(Candidate::class))
        ;

        $service = new FifoMatchMakingService($repo, $launcher);

        $service->findMatches();
    }
}

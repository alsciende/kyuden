<?php

declare(strict_types=1);

namespace App\Service\Tournament;

use App\Entity\Tournament;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\Alarm;
use Eluceo\iCal\Domain\ValueObject\Alarm\DisplayAction;
use Eluceo\iCal\Domain\ValueObject\Alarm\RelativeTrigger;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\Clock\ClockInterface;

/**
 * A tournament as an iCalendar file (RFC 5545), to add it to a calendar: one event at its start, in UTC, with an
 * approximate end (a game has no time limit), a reminder when everything locks, and the link to its page. Its uid is the
 * id of the tournament, so that importing the file again updates the same event; a cancelled tournament keeps its file,
 * marked cancelled.
 */
final readonly class TournamentCalendar
{
    /**
     * The approximate length of a tournament, for the end of the event.
     */
    public const string LENGTH = '3 hours';

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string $pageUrl the URL of the page of the tournament
     */
    public function createIcs(Tournament $tournament, string $pageUrl): string
    {
        $startsAt = $tournament->getStartsAt()->setTimezone(new \DateTimeZone('UTC'));
        $organizer = $tournament->getOrganizer()->getName();

        $event = new Event(new UniqueIdentifier($tournament->getId() . '@kyuden.app'))
            ->touch(new Timestamp($this->clock->now()))
            ->setSummary('Kyūden tournament')
            ->setDescription(implode("\n", [
                "Organized by {$organizer}.",
                'Registrations and decks lock one hour before the start.',
                $pageUrl,
            ]))
            ->setUrl(new Uri($pageUrl))
            ->setOccurrence(new TimeSpan(
                new DateTime($startsAt, true),
                new DateTime($startsAt->modify('+' . self::LENGTH), true),
            ))
            ->addAlarm(new Alarm(
                new DisplayAction('Kyūden tournament: registrations and decks lock now'),
                new RelativeTrigger(\DateInterval::createFromDateString('-' . Tournament::LOCK_LEAD))->withRelationToStart(),
            ));

        if ($tournament->isCancelled()) {
            $event->setStatus(EventStatus::CANCELLED());
        }

        $calendar = new Calendar([$event])->setProductIdentifier('-//Kyūden//Tournaments//EN');

        return (string) new CalendarFactory()->createCalendar($calendar);
    }
}

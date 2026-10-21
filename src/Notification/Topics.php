<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * The topics of the Mercure hub. Every update is private: a subscriber needs a token (`SubscriptionFactory`) whose
 * `subscribe` claim matches one of the topics of the update. An update of a game also carries the topic of the game
 * for each of its players (`/users/{user}/games/{game}`), so that the token of a user, a template of the topics of their
 * games, gives access to every game they play without being issued again when they join one.
 */
final class Topics
{
    public static function game(string $gameId): string
    {
        return '/games/' . $gameId;
    }

    public static function user(string $userId): string
    {
        return '/users/' . $userId;
    }

    public static function gameOfUser(string $userId, string $gameId): string
    {
        return self::user($userId) . '/games/' . $gameId;
    }

    /**
     * The topic selectors a user may subscribe to: their own topic and the games they play.
     *
     * @return list<string>
     */
    public static function subscribableBy(string $userId): array
    {
        return [self::user($userId), self::user($userId) . '/games/{gameId}'];
    }
}

<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * The topics of the Mercure hub. Every update is private: a subscriber needs a token (`SubscriptionFactory`) whose
 * `subscribe` grant matches one of the topics of the update. An update of a game also carries the topic of the game
 * for each of its players (`/users/{user}/games/{game}`), so that the token of a user, a URL pattern of the topics of
 * their games, gives access to every game they play without being issued again when they join one.
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
     * The topics a user may subscribe to, by matcher type of the protocol 1.0: their own topic, and the games they play
     * (a URL pattern, resolved against the resource identifier of the hub like the topics).
     *
     * @return array{exact: list<string>, urlpattern: list<string>}
     */
    public static function subscribableBy(string $userId): array
    {
        // the characters of the syntax of URL patterns, which an id must not bring in
        $userPattern = addcslashes(self::user($userId), '\\:*?+(){}');

        return ['exact' => [self::user($userId)], 'urlpattern' => [$userPattern . '/games/:gameId']];
    }
}

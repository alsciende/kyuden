<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Game;

/**
 * Translates the ids of a game into those of its replay, a copy of the game under another id.
 *
 * The ids of the cards and stacks are salted by the id of the game (Game::generateId()): the replay draws the same
 * values as the original, and an id of the original becomes the id of the replay once its salt is swapped (XOR with
 * both salts). The ids of the players are translated the same way: the translation only has to be the same
 * everywhere. The version and variant bits of an id are kept. What does not belong to the game (the user and the deck
 * of a player joining it) is kept.
 */
final readonly class ReplayIdTranslator
{
    /**
     * Keys of a payload naming what does not belong to the game.
     */
    private const array KEPT_KEYS = ['user_id', 'deck_id'];

    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * XOR of both salts, without the version and variant bits.
     */
    private string $key;

    public function __construct(
        public string $originalGameId,
        public string $replayGameId,
    ) {
        $key = Game::idSalt($originalGameId) ^ Game::idSalt($replayGameId);
        $key[6] = chr(ord($key[6]) & 0x0F);
        $key[8] = chr(ord($key[8]) & 0x3F);
        $this->key = $key;
    }

    /**
     * The id of the replay for an id of the original game: a string that is not an id is returned as is.
     */
    public function translateId(string $id): string
    {
        if ($id === $this->originalGameId) {
            return $this->replayGameId;
        }
        if (preg_match(self::UUID, $id) !== 1) {
            return $id;
        }

        $hex = bin2hex((hex2bin(str_replace('-', '', $id)) ?: '') ^ $this->key);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    /**
     * Every id in a decoded JSON value (the payload of a message, the params of a log entry), keys included; a JSON
     * object decoded as an object stays one.
     */
    public function translate(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->translateId($value);
        }
        if ($value instanceof \stdClass) {
            return (object) $this->translate((array) $value);
        }
        if (! is_array($value)) {
            return $value;
        }

        $translated = [];
        foreach ($value as $key => $item) {
            if (in_array($key, self::KEPT_KEYS, true)) {
                $translated[$key] = $item;
                continue;
            }
            $translated[is_string($key) ? $this->translateId($key) : $key] = $this->translate($item);
        }

        return $translated;
    }

    /**
     * The JSON payload of a message of the original game, for its replay.
     */
    public function translatePayload(string $payload): string
    {
        return json_encode($this->translate(json_decode($payload, flags: JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);
    }
}

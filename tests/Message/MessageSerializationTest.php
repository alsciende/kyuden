<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Enum\ConflictType;
use App\Enum\Element;
use App\Message\DeclareConflict;
use App\Tests\AbstractKernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Messages are stored as events and replayed (EventPlayer): they must survive a round trip through the serializer.
 */
class MessageSerializationTest extends AbstractKernelTestCase
{
    public function testAMessageWithEnumsAndListsSurvivesARoundTrip(): void
    {
        $serializer = self::service(SerializerInterface::class);
        $message = new DeclareConflict('game', 'player', Element::Void, ConflictType::Political, 'province', ['a', 'b'], ['c']);

        $copy = $serializer->deserialize($serializer->serialize($message, 'json'), DeclareConflict::class, 'json');

        self::assertEquals($message, $copy);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security\OAuth;

use App\Enum\OAuthProvider;
use App\Security\OAuth\OAuthProviderFactory;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class OAuthProviderFactoryTest extends TestCase
{
    public function testAProviderWithoutAClientIdIsDisabled(): void
    {
        $factory = new OAuthProviderFactory([
            'discord' => ['client_id' => '', 'client_secret' => ''],
            'google' => ['client_id' => 'google-client', 'client_secret' => 'google-secret'],
        ], new Client());

        self::assertFalse($factory->isEnabled(OAuthProvider::Discord));
        self::assertTrue($factory->isEnabled(OAuthProvider::Google));
        self::assertSame([OAuthProvider::Google], $factory->getEnabledProviders());

        $this->expectException(\LogicException::class);
        $factory->create(OAuthProvider::Discord, 'http://localhost/oauth/discord/callback');
    }
}

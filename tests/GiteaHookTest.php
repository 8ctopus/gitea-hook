<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Oct8pus\GiteaHook;
use Tests\TestCase;

/**
 * @internal
 *
 * @covers \Oct8pus\GiteaHook
 */
final class GiteaHookTest extends TestCase
{
    private static array $commands;

    public static function setUpBeforeClass(): void
    {
        $path = __DIR__;

        static::$commands = [
            'site' => [
                // pull and run composer
                "cd {$path}",
                "git status",
                "composer install --no-interaction",
            ],
            'store' => [
                "cd {$path}",
                "git status",
                "composer install --no-interaction",
            ],
        ];
    }

    public function testOK() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $payload = json_encode($payload);

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();

        static::assertTrue(true);
    }

    public function testNoSection() : void
    {
        $this->mockRequest('GET', '', [], []);

        static::expectException(Exception::class);
        static::expectExceptionMessage('no section');

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testUnknownSection() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'unknown',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('unknown section - unknown');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNotPostRequest() : void
    {
        $this->mockRequest('GET', '', [
            'section' => 'site',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('not a POST request - GET');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testNoPayload() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('no payload');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testHeaderSignatureMissing() : void
    {
        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => 'test',
        ]);

        static::expectException(Exception::class);
        static::expectExceptionMessage('header signature missing');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, 'SECRET_KEY'))
            ->run();
    }

    public function testInvalidPayloadSignature() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = 'invalid signature';

        static::expectException(Exception::class);
        static::expectExceptionMessage('payload signature');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }

    public function testPayloadJsonDecode() : void
    {
        $secretKey = 'sd90sfufj';
        $payload = 'test';

        $this->mockRequest('POST', '', [
            'section' => 'site',
        ], [
            'payload' => $payload,
        ]);

        $_SERVER['HTTP_X_GITEA_SIGNATURE'] = hash_hmac('sha256', $payload, $secretKey, false);

        static::expectException(Exception::class);
        static::expectExceptionMessage('json decode - 4');
        static::expectExceptionCode(401);

        (new GiteaHook(static::$commands, $secretKey, null))
            ->run();
    }
}

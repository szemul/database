<?php
declare(strict_types=1);

namespace Szemul\Database\Test\Config;

use PHPUnit\Framework\TestCase;
use Szemul\Database\Config\AccessConfig;

class AccessConfigTest extends TestCase
{
    public function testDebugInfo(): void
    {
        $sut      = new AccessConfig('testHost', 'testUser', 'testPass', 1234);
        $expected = [
            'host'     => 'testHost',
            'port'     => 1234,
            'username' => '*** REDACTED ***',
            'password' => '*** REDACTED ***',
        ];

        $this->assertEquals($expected, $sut->__debugInfo());
    }

    public function testGetters(): void
    {
        $sut = new AccessConfig('testHost', 'testUser', 'testPass', 1234);

        $this->assertSame('testHost', $sut->getHost());
        $this->assertSame('testUser', $sut->getUsername());
        $this->assertSame('testPass', $sut->getPassword());
        $this->assertSame(1234, $sut->getPort());
    }
}

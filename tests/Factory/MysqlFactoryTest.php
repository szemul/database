<?php
declare(strict_types=1);

namespace Szemul\Database\Test\Factory;

use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Szemul\Database\Connection\MysqlConnection;
use Szemul\Database\Factory\MysqlFactory;

class MysqlFactoryTest extends TestCase
{
    private MysqlConnection|MockInterface|LegacyMockInterface $readOnlyConnection;
    private MysqlConnection|MockInterface|LegacyMockInterface $readWriteConnection;
    private MysqlFactory                                      $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->readOnlyConnection  = Mockery::mock(MysqlConnection::class);
        $this->readWriteConnection = Mockery::mock(MysqlConnection::class);

        $this->sut = new MysqlFactory($this->readOnlyConnection, $this->readWriteConnection); //@phpstan-ignore-line
    }

    public function testGetReadOnly(): void
    {
        $this->assertSame($this->readOnlyConnection, $this->sut->getReadOnly());
    }

    public function testGetReadWrite(): void
    {
        $this->assertSame($this->readWriteConnection, $this->sut->getReadWrite());
    }

    public function testGetWithReadOnly_shouldReturnReadOnly(): void
    {
        $this->assertSame($this->readOnlyConnection, $this->sut->get(true));
    }

    public function testGetWithReadWrite_shouldReturnReadWrite(): void
    {
        $this->assertSame($this->readWriteConnection, $this->sut->get());
    }
}

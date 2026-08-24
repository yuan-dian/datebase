<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Integration;

use PHPUnit\Framework\TestCase;

class MysqlCharsetTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('Requires live MySQL at 127.0.0.1');
    }

    public function testUtf8Charset(): void {}

    public function testCharsetInjectionPrevention(): void {}
}

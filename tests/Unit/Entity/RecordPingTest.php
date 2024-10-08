<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 8. 10. 2024
 * Time: 21:37
 */

namespace BugCatcher\Tests\Unit\Entity;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPing;
use LogicException;
use PHPUnit\Framework\TestCase;

class RecordPingTest extends TestCase
{
    public function testCalculateHash(): void
    {
        $record = new RecordPing(new Project(), "200");
        $this->expectException(LogicException::class);
        $record->calculateHash();
    }

    public function testComponentName(): void
    {
        $record = new RecordPing(new Project(), "200");
        $this->expectException(LogicException::class);
        $record->getComponentName();
    }

    public function testIsNotError(): void
    {
        $record = new RecordPing(new Project(), "200");
        $this->assertFalse($record->isError());
    }

    public function testIsError(): void
    {
        $record = new RecordPing(new Project(), "500");
        $this->assertTrue($record->isError());
    }

}
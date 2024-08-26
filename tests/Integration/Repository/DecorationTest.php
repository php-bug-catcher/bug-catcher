<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 8. 2024
 * Time: 19:48
 */

namespace BugCatcher\Tests\Integration\Repository;

use BugCatcher\Repository\RecordRepositoryInterface;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\App\Repository\RecordRepository;

class DecorationTest extends KernelTestCase
{
    public function testRecordRepositoryDecorator():void
    {
        $decorator= self::getContainer()->get(RecordRepositoryInterface::class);
        $this->assertInstanceOf(RecordRepository::class, $decorator);
    }
}
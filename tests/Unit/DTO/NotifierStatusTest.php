<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 8. 10. 2024
 * Time: 21:07
 */

namespace BugCatcher\Tests\Unit\DTO;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\Importance;
use PHPUnit\Framework\TestCase;

class NotifierStatusTest extends TestCase
{
    public function testLevelUp(): void
    {
        $status = new NotifierStatus(new Project());
        $group = Importance::min();
        foreach (Importance::all() as $targetImportance) {
            $this->assertEquals($targetImportance, $status->getImportance());
            $status->levelUp($group);
        }
        $this->assertEquals($targetImportance, $status->getImportance());
    }

    public function testSaveLevelTwoGroups(): void
    {
        $status = new NotifierStatus(new Project());
        $first = Importance::min();
        $second = Importance::max();
        $status->ok($second);
        $this->assertEquals(Importance::min(), $status->getImportance());
        foreach (Importance::all() as $targetImportance) {
            $status->levelUp($first);
        }
        $this->assertEquals(Importance::min(), $status->getImportance());
    }

    public function testLevelUpTwoGroups(): void
    {
        $status = new NotifierStatus(new Project());
        $first = Importance::min();
        $second = Importance::max();
        $status->ok($first);
        $this->assertEquals(Importance::min(), $status->getImportance());
        foreach (Importance::all() as $targetImportance) {
            $status->levelUp($second);
        }
        $this->assertEquals(Importance::max(), $status->getImportance());
    }

    public function testOk()
    {
        $status = new NotifierStatus(new Project());
        $group = Importance::min();
        foreach (Importance::all() as $targetImportance) {
            $status->levelUp($group);
        }
        $status->ok($group);
        $this->assertEquals(Importance::min(), $status->getImportance());
    }

    public function testDanger()
    {
        $status = new NotifierStatus(new Project());
        $group = Importance::min();
        $status->levelUp($group);
        $status->danger($group);
        $this->assertEquals(Importance::max(), $status->getImportance());
    }
}
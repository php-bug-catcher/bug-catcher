<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 22. 9. 2024
 * Time: 19:51
 */

namespace BugCatcher\Tests\Unit\Enum;

use BugCatcher\Enum\BootstrapColor;
use BugCatcher\Enum\Importance;
use PHPUnit\Framework\TestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ImportanceTest extends TestCase
{

    public function testMin(): void
    {
        $all = Importance::all();
        $min = Importance::min();
        $this->assertSame($min, $all[0]);
    }


    public function testMax(): void
    {
        $all = Importance::all();
        $max = Importance::max();
        $this->assertSame($max, $all[count($all) - 1]);
    }

    public function testGetColor(): void
    {
        foreach (Importance::all() as $importance) {
            $color = $importance->getColor();
            $this->assertInstanceOf(BootstrapColor::class, $color);
        }
    }

    public function testIsHigher(): void
    {
        $all = Importance::all();
        $max = array_pop($all);
        foreach ($all as $importance) {
            $this->assertTrue($max->isHigherThan($importance));
            $prev = $importance;
        }
    }


    public function testHigher(): void
    {
        $prevMax = Importance::min();
        $currentMax = $prevMax->higher();
        $loop = 0;
        while ($currentMax !== Importance::max() && $loop < 100) {
            $this->assertTrue($currentMax->isHigherThan($prevMax));
            $prevMax = $currentMax;
            $currentMax = $currentMax->higher();
            $loop++;
        }
        $this->assertNotSame($loop, 100);
    }
}

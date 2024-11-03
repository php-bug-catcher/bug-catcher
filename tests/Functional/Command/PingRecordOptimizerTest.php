<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 11. 9. 2024
 * Time: 20:30
 */

namespace BugCatcher\Tests\Functional\Command;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;

class PingRecordOptimizerTest extends KernelTestCase
{
    //use ResetDatabase;
    /**
     * @dataProvider logsProvider
     */
    public function testAlwaysOkCollector(int $count, int $steps, int $precision, int $targetCount): void
    {
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'always_ok',
        ]);
        self::bootKernel();

        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $startDate = (new DateTimeImmutable("midnight"))->modify("-1minute");
        for ($i = 0; $i < $steps; $i++) {
            RecordPingFactory::createMany($count, [
                'date' => $startDate->modify("-" . ($i) . " minutes"),
                "project" => $project,
                "statusCode" => Response::HTTP_OK
            ]);
//            RecordPingFactory::createMany($count, [
//                'date' => new \DateTimeImmutable("-".($i)." minutes"),
//                "project" => $project,
//                "statusCode" => Response::HTTP_INTERNAL_SERVER_ERROR
//            ]);
        }

        $application = new Application(self::$kernel);
        $command = $application->find('app:record-optimizer');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), "--past" => 0, "--precision" => $precision));
        $records = RecordPingFactory::findBy([
            "statusCode" => Response::HTTP_OK
        ]);
        $this->assertSame($targetCount, count($records));
        for ($pos = 0; $pos < $targetCount; $pos++) {
            $date = new DateTimeImmutable("+" . ($pos * $precision) . "minutes");

        }
    }

    public function logsProvider(): iterable
    {
        yield [10, 35, 10, 4];
        yield [1, 40, 30, 2];
    }

}
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
use BugCatcher\Tests\App\Message\BlankMessage;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

class PingCollectorTest extends KernelTestCase
{
    use ResetDatabase;

    public function testAlwaysOkCollector()
    {
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'always_ok',
        ]);

        $application = new Application(self::$kernel);

        $command = $application->find('app:ping-collector');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(1, $count);
        $record = RecordPingFactory::first();
        $this->assertEquals(Response::HTTP_OK, $record->getStatusCode());
    }

    public function testNullCollector()
    {
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'not-found',
        ]);

        $application = new Application(self::$kernel);

        $command = $application->find('app:ping-collector');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(0, $count);
    }

    public function testThrowCollector()
    {
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'always_throw',
        ]);

        $application = new Application(self::$kernel);

        $command = $application->find('app:ping-collector');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(1, $count);
        $record = RecordPingFactory::first();
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $record->getStatusCode());
    }

    public function testHttpCollector()
    {
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'http',
            "url" => 'https://www.google.com/',
        ]);
        $this->getContainer()->get(MessageBusInterface::class)->dispatch(new BlankMessage());

        $application = new Application(self::$kernel);

        $command = $application->find('app:ping-collector');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(1, $count);
        $record = RecordPingFactory::first();
        $this->assertEquals(Response::HTTP_OK, $record->getStatusCode());
    }

    public function testMessengerCollector()
    {
        date_default_timezone_set('UTC');
        ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "pingCollector" => 'messenger',
            "dbConnection" => 'default',
        ]);
        $this->getContainer()->get(MessageBusInterface::class)->dispatch(new BlankMessage());

        $application = new Application(self::$kernel);

        $command = $application->find('app:ping-collector');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(1, $count);
        $record = RecordPingFactory::first();
        $this->assertEquals(Response::HTTP_OK, $record->getStatusCode());

        RecordPingFactory::truncate();

        date_default_timezone_set('Europe/Bratislava');

        $commandTester->execute(array('command' => $command->getName()));

        $count = RecordPingFactory::count();
        $this->assertEquals(1, $count);
        $record = RecordPingFactory::first();
        $this->assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $record->getStatusCode());
    }
}
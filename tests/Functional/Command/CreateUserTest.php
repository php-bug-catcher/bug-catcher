<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 11. 9. 2024
 * Time: 20:30
 */

namespace BugCatcher\Tests\Functional\Command;

use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\ResetDatabase;

class CreateUserTest extends KernelTestCase
{
    use ResetDatabase;

    public function testCreateUser()
    {


        $application = new Application(self::$kernel);

        $command = $application->find('app:create-user');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array('command' => $command->getName(), 'username' => 'admin', 'password' => 'admin'));

        $count = UserFactory::count();
        $this->assertEquals(1, $count);
        $user = UserFactory::first();
        $this->assertEquals('admin', $user->getEmail());
        $this->assertTrue($user->isEnabled());
        $this->assertEquals(['ROLE_ADMIN', "ROLE_USER"], $user->getRoles());
        $this->assertNotEmpty($user->getPassword());

    }

}
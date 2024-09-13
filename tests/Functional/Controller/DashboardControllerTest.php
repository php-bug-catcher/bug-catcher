<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 13. 9. 2024
 * Time: 13:05
 */

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use Doctrine\Common\Collections\ArrayCollection;

class DashboardControllerTest extends KernelTestCase
{
    use apiTestHelper;

    public function testUserLists(): void
    {
        $user = UserFactory::createOne([
        ]);
        $this->loginUser($user->_real());
        [$browser] = $this->browser([]);

        $browser
            ->get("/admin")
            ->assertStatus(200);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 13. 9. 2024
 * Time: 13:05
 */

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Controller\Admin\NotifierCrudController;
use BugCatcher\Controller\Admin\NotifierEmailCrudController;
use BugCatcher\Controller\Admin\NotifierFaviconCrudController;
use BugCatcher\Controller\Admin\NotifierSoundCrudController;
use BugCatcher\Controller\Admin\ProjectCrudController;
use BugCatcher\Controller\Admin\RecordWithholderCrudController;
use BugCatcher\Controller\Admin\UserCrudController;
use BugCatcher\Entity\User;
use BugCatcher\Tests\App\Factory\NotifierEmailFactory;
use BugCatcher\Tests\App\Factory\NotifierFaviconFactory;
use BugCatcher\Tests\App\Factory\NotifierSoundFactory;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogWithholderFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

class UserControllerTest extends KernelTestCase
{
    use apiTestHelper;

    /**
     * @dataProvider controllersProvider
     */
    public function testLists(string $controller, string $factory): void
    {
        [$browser] = $this->browser([]);

        $browser
            ->get("/admin?crudAction=index&crudControllerFqcn={$controller}")
            ->assertStatus(200);
    }

    /**
     * @param PersistentProxyObjectFactory $factory
     * @dataProvider controllersProvider
     */
    public function testEdit(string $controller, string $factory): void
    {
        /** @var User $entity */
        $entity = $factory::createOne([
        ]);
        [$browser] = $this->browser([]);

        $browser
            ->get("/admin?crudAction=edit&crudControllerFqcn={$controller}&entityId={$entity->getId()->toString()}")
            ->assertStatus(200);
    }


    public function controllersProvider(): iterable
    {
        yield [UserCrudController::class, UserFactory::class];
        yield [NotifierEmailCrudController::class, NotifierEmailFactory::class];
        yield [NotifierFaviconCrudController::class, NotifierFaviconFactory::class];
        yield [NotifierSoundCrudController::class, NotifierSoundFactory::class];
        yield [ProjectCrudController::class, ProjectFactory::class];
        yield [RecordWithholderCrudController::class, RecordLogWithholderFactory::class];

    }
}
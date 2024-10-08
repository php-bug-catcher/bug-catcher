<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 8. 10. 2024
 * Time: 21:00
 */

namespace BugCatcher\Tests\Functional\Extension;

use BugCatcher\Entity\Project;
use BugCatcher\Service\DashboardImportance;
use BugCatcher\Tests\App\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;

class TypeFunctionTest extends KernelTestCase
{

    public function testNotDiscriminationMap(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get(EntityManagerInterface::class);
        $this->expectException(QueryException::class);
        $em->createQueryBuilder()
            ->select('TYPE(project) as type')
            ->from(Project::class, 'project')
            ->getQuery()->getResult();
    }

}
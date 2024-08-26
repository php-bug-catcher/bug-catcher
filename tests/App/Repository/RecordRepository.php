<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:53
 */

namespace BugCatcher\Tests\App\Repository;

use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepositoryInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;


#[AsDecorator(decorates: \BugCatcher\Repository\RecordRepositoryInterface::class)]
class RecordRepository  implements RecordRepositoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private \BugCatcher\Repository\RecordRepository $inner,
    ) {
    }


    public function setStatusOlderThan(
        array $projects,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCreator = null
    ): void {
        $this->inner->setStatusOlderThan(
            $projects,
            $lastDate,
            $newStatus,
            $previousStatus,
            $qbCreator
        );
    }

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCreator = null
    ) {
        $this->inner->setStatus(
            $log,
            $lastDate,
            $newStatus,
            $previousStatus,
            $flush,
            $qbCreator
        );
    }

    public function find($id)
    {
        return $this->inner->find($id);
    }

    public function findAll()
    {
        return $this->inner->findAll();
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)
    {
        return $this->inner->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria)
    {
        return $this->inner->findOneBy($criteria);
    }

    public function getClassName()
    {
        return $this->inner->getClassName();
    }

    public function matching(Criteria $criteria)
    {
        return $this->inner->matching($criteria);
    }
}

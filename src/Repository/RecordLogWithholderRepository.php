<?php

namespace BugCatcher\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogWithholder;
use BugCatcher\Entity\RecordStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<RecordLogWithholder>
 *
 * @method RecordLogWithholder|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordLogWithholder|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordLogWithholder[]    findAll()
 * @method RecordLogWithholder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordLogWithholderRepository extends ServiceEntityRepository
{
	public function __construct(
		ManagerRegistry $registry,
	) {
        parent::__construct($registry, RecordLogWithholder::class);
    }

}

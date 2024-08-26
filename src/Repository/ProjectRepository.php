<?php

namespace BugCatcher\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\User;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class ProjectRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, Project::class);
	}

	public function save(Project $entity, bool $flush = false): void {
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(Project $entity, bool $flush = false): void {
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function createEmpty(bool $flush): Project {
		$entity = new Project();

		$this->save($entity, $flush);

		return $entity;
	}

	public function getQBWith(?bool $enabled = null): QueryBuilder {
		$qb = $this->createQueryBuilder('p');

		if ($enabled !== null) {
			$qb->andWhere('p.enabled = :enabled')->setParameter('enabled', $enabled);
		}

		return $qb;
	}

	public function getQBBlank(): QueryBuilder {
		return $this->createQueryBuilder('p')->setMaxResults(0);
	}

	/**
	 * @param User|null $user
	 * @return array<Project>
	 */
	public function findByAdmin(?User $user): array {
		$qb = $this->createQueryBuilder('p');
		$qb
			->join("p.users", "u")
			->where('u.id = :admin')
			->setParameter('admin', $user->getId(), UuidType::NAME);

		return $qb->getQuery()->getResult();
	}

}

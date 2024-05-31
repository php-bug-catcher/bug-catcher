<?php

namespace PhpSentinel\BugCatcher\Repository;

use PhpSentinel\BugCatcher\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, User::class);
	}

	/**
	 * Used to upgrade (rehash) the user's password automatically over time.
	 */
	public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void {
		if (!$user instanceof User) {
			throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
		}

		$user->setPassword($newHashedPassword);
		$this->getEntityManager()->persist($user);
		$this->getEntityManager()->flush();
	}

	public function remove(User $entity, bool $flush = false): void {
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function createEmpty(string $email, bool $flush): User {
		$entity = new User();
		$entity
			->setEmail($email)
			->setRoles([])
			->setPassword("empty password")
			->setFullname("John Doe");

		$this->save($entity, $flush);

		return $entity;
	}

	public function save(User $entity, bool $flush = false): void {
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function getQBWith(): QueryBuilder {
		$qb = $this->createQueryBuilder('u');

		return $qb;
	}

	public function getQBBlank(): QueryBuilder {
		return $this->createQueryBuilder('u')->setMaxResults(0);
	}
}

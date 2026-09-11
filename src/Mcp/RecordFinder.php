<?php

namespace BugCatcher\Mcp;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Reads the collected errors. `RecordRepositoryInterface` only writes, and the dashboard builds its
 * queries inside the Twig components, so the MCP tools would have nothing to reuse.
 *
 * Queries are rooted at {@see RecordLog} rather than at {@see Record}: everything the ingest API
 * accepts is a `RecordLog` or a subclass of one, which is also what carries `level`, `message` and
 * `requestUri`. The one other `Record` subtype, `RecordPing`, is the result of an uptime check and
 * is nothing anyone fixes in a code base.
 */
final class RecordFinder
{
	private const DATE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * How far back the history of a single error is read. Matches the dashboard's detail page.
	 */
	private const HISTORY_LIMIT = 50;

	public function __construct(private readonly EntityManagerInterface $em) {}

	/**
	 * The distinct errors matching the criteria, most recently seen first.
	 *
	 * Records repeat - one bug reported a thousand times is a thousand rows sharing a hash - so the
	 * result holds one record per hash, the latest occurrence, carrying the size of its group in
	 * `getCount()` and the oldest occurrence in `getFirstOccurrence()`.
	 *
	 * @return RecordLog[]
	 */
	public function search(RecordSearchCriteria $criteria): array {
		$groups = $this->groups($criteria);
		if ($groups === []) {
			return [];
		}

		$representatives = $this->representatives($criteria, $groups);

		$found = [];
		// driven by $groups, so the "most recently seen first" order of the grouping query survives
		foreach ($groups as $hash => $group) {
			if (!isset($representatives[$hash])) {
				continue;
			}
			$found[] = $representatives[$hash]
				->setCount($group['occurrences'])
				->setFirstOccurrence($group['firstOccurrence']);
		}

		return $found;
	}

	/**
	 * The record behind an id handed out by {@see search()}.
	 *
	 * Returns the concrete subclass, so a caller can hand it to the repository that knows about it.
	 */
	public function find(Uuid $id): ?RecordLog {
		return $this->em->getRepository(RecordLog::class)->find($id);
	}

	/**
	 * The occurrences of one error, newest first, collapsed per timestamp.
	 *
	 * Several reports can share a second, and listing them as separate lines would read as if the
	 * bug happened at the same instant twice; `getCount()` carries how many there were instead.
	 *
	 * @return RecordLog[]
	 */
	public function history(Record $record): array {
		/** @var RecordLog[] $occurrences */
		$occurrences = $this->em->getRepository(RecordLog::class)->findBy(
			['hash' => $record->getHash(), 'status' => $record->getStatus()],
			['date' => 'DESC'],
			self::HISTORY_LIMIT,
		);

		$collapsed = [];
		foreach ($occurrences as $occurrence) {
			$key = $occurrence->getDate()->getTimestamp();
			// the entities are shared with whatever else holds them this request, so the counter
			// starts from a value this method set, never from one left behind by a previous read
			$collapsed[$key] ??= $occurrence->setCount(0);
			$collapsed[$key]->setCount($collapsed[$key]->getCount() + 1);
		}

		return array_values($collapsed);
	}

	/**
	 * One row per hash: how many records it holds and the span they cover.
	 *
	 * Counting in SQL rather than over a fetched page is what makes `getCount()` the whole truth.
	 * A reader ranking bugs by how often they happen would be misled by "how many fit in the page".
	 *
	 * @return array<string, array{occurrences: int, firstOccurrence: DateTimeImmutable}>
	 */
	private function groups(RecordSearchCriteria $criteria): array {
		$qb = $this->em->createQueryBuilder()
			->select(
				'record.hash AS hash',
				'COUNT(record.id) AS occurrences',
				'MIN(record.date) AS firstOccurrence',
				'MAX(record.date) AS lastOccurrence',
			)
			->from(RecordLog::class, 'record')
			->groupBy('record.hash')
			->orderBy('lastOccurrence', 'DESC')
			->setMaxResults($criteria->limit);

		$rows = $this->applyFilters($qb, $criteria)->getQuery()->getArrayResult();

		$groups = [];
		foreach ($rows as $row) {
			$groups[$row['hash']] = [
				'occurrences'     => (int)$row['occurrences'],
				'firstOccurrence' => new DateTimeImmutable($row['firstOccurrence']),
				'lastOccurrence'  => new DateTimeImmutable($row['lastOccurrence']),
			];
		}

		return $groups;
	}

	/**
	 * The latest record of each group, hydrated.
	 *
	 * Bounded by both the hashes and their last occurrence, so this reads at most a handful of rows
	 * per group instead of the whole history of every one of them.
	 *
	 * @param array<string, array{lastOccurrence: DateTimeImmutable}> $groups
	 *
	 * @return array<string, RecordLog>
	 */
	private function representatives(RecordSearchCriteria $criteria, array $groups): array {
		$dates = [];
		foreach ($groups as $group) {
			$dates[] = $group['lastOccurrence']->format(self::DATE_FORMAT);
		}

		$qb = $this->em->createQueryBuilder()
			->select('record')
			->from(RecordLog::class, 'record')
			->where('record.hash IN (:hashes)')
			->andWhere('record.date IN (:dates)')
			->setParameter('hashes', array_keys($groups), ArrayParameterType::STRING)
			->setParameter('dates', array_unique($dates), ArrayParameterType::STRING);

		// the same filters again: a hash is unique per project, but not per status, so without them
		// a resolved record could stand in for the unresolved group that was asked for
		/** @var RecordLog[] $candidates */
		$candidates = $this->applyFilters($qb, $criteria)->getQuery()->getResult();

		$representatives = [];
		foreach ($candidates as $candidate) {
			$hash = $candidate->getHash();
			if (isset($representatives[$hash]) || !isset($groups[$hash])) {
				continue;
			}
			// several groups share the date list, so each candidate still has to prove it is the
			// latest record of its own group
			if ($candidate->getDate()->format(self::DATE_FORMAT) === $groups[$hash]['lastOccurrence']->format(self::DATE_FORMAT)) {
				$representatives[$hash] = $candidate;
			}
		}

		return $representatives;
	}

	private function applyFilters(QueryBuilder $qb, RecordSearchCriteria $criteria): QueryBuilder {
		if ($criteria->project !== null) {
			// the raw binary column value, the way LogList binds it - handing over the entity or the
			// Uuid object matches nothing
			$qb->andWhere('record.project = :project')
				->setParameter('project', $criteria->project->getId()->toBinary());
		}
		if ($criteria->status !== null) {
			// a prefix, like the dashboard: "new" also finds the withheld variants the notification
			// throttle parks under a longer name
			$qb->andWhere('record.status LIKE :status')->setParameter('status', $criteria->status . '%');
		}
		if ($criteria->code !== null) {
			$qb->andWhere('record.code = :code')->setParameter('code', $criteria->code);
		}
		if ($criteria->minLevel !== null) {
			$qb->andWhere('record.level >= :minLevel')->setParameter('minLevel', $criteria->minLevel);
		}
		if ($criteria->from !== null) {
			$qb->andWhere('record.date >= :from')->setParameter('from', $criteria->from);
		}
		if ($criteria->to !== null) {
			$qb->andWhere('record.date <= :to')->setParameter('to', $criteria->to);
		}

		return $qb;
	}
}

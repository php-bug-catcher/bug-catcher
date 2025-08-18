<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
final class LogList extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $status;

    #[LiveProp]
    public ?Project $project = null;

    #[LiveProp]
    public string $id;
    /** @var Record[] */
    #[ExposeInTemplate]
    public array $logs = [];
    #[ExposeInTemplate]
    public ?DateTimeInterface $from = null;
    #[ExposeInTemplate]
    public ?DateTimeInterface $to = null;
	#[LiveProp(writable: true)]
	public string $query = '';

    public function __construct(
        private readonly RecordRepository $recordRepo,
        private ManagerRegistry $registry,
        private array $classes
    ) {
        $this->id = uniqid();
    }

    public function init(): void
    {

        $em = $this->registry->getManager();
        $classMetadata = $em->getClassMetadata(Record::class);
        $discriminatorMap = $classMetadata->discriminatorMap;
        $discriminatorMap = array_flip($discriminatorMap);
        $keys = array_map(fn($class) => $discriminatorMap[$class] ?? null, $this->classes);

        if ($this->project) {
            $projects = [$this->project];
        } else {
            $projects = $this->getUser()->getActiveProjects()->toArray();
        }
        /** @var Record[] $records */
		$qb = $this->recordRepo->createQueryBuilder("record")
			->where("record.status like :status")
			->andWhere("record INSTANCE OF :class")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", $this->status . '%')
			->setParameter("class", $keys)
			->setParameter('projects',
				array_map(fn(Project $p) => $p->getId()->toBinary(), $projects)
			)
			->orderBy("record.date", "DESC")
			->setMaxResults(100);

		if ($this->query) {
			$qb->andWhere("record.code = :query")
				->setParameter("query", $this->query);
		}

		$records = $qb
            ->getQuery()->getResult();

        if ($records === []) {
            return;
        }
        $this->from = $records[0]->getDate();
        $this->to = $records[count($records) - 1]->getDate();


        $logs = [];
        foreach ($records as $row) {
            $record = $logs[$row->getHash()] = $logs[$row->getHash()] ?? $row->setCount(0);
            $record->setCount($record->getCount() + 1);
            $record->setFirstOccurrence($row->getDate());
        }

        $this->logs = array_values($logs);
    }


    #[LiveAction]
    public function clearAll(
        #[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeInterface $from,
        #[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeInterface $to,
    ) {
        $rows = $this->recordRepo->createQueryBuilder("record")
            ->addSelect('TYPE(record) as type')
            ->where("record.status = :status")
            ->andWhere("record.date BETWEEN :from AND :to")
            ->setParameter("status", $this->status)
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->groupBy('type')
            ->getQuery()->getResult();

        foreach ($rows as $row) {
            $class = $row['0']::class;
            $repo = $this->registry->getRepository($class);
            $repo->setStatusBetween(
                $this->getUser()->getProjects()->toArray(),
                $from,
                $to,
                'resolved', $this->status
            );

        }

    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 5. 10. 2023
 * Time: 20:38
 */
namespace BugCatcher\Twig\Components\LogList;

use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;


#[AsLiveComponent(template: '@BugCatcher/components/LogList/RecordLog.html.twig')]
final class RecordLog
{
	use DefaultActionTrait;

	#[LiveProp]
	public ?Record $log;
	#[LiveProp]
	public string $status;


	public function __construct(
		private ManagerRegistry $registry,
		private array           $classes
	) {}

	#[LiveAction]
	public function clearOne(
        #[LiveArg] string $status,
        #[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeInterface $from,
	) {
		if (!$this->log) {
			return;
		}
		$class = $this->log::class;
		$repo  = $this->registry->getRepository($class);
		assert($repo instanceof RecordRepository);
        $repo->setStatus($this->log, $from, $status, $this->status, true);
		$this->log = null;
	}
}
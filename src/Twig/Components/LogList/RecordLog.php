<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 5. 10. 2023
 * Time: 20:38
 */
namespace PhpSentinel\BugCatcher\Twig\Components\LogList;

use Doctrine\Persistence\ManagerRegistry;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;


#[AsLiveComponent(template: '@BugCatcher/components/LogList/RecordLog.html.twig')]
class RecordLog {
	use DefaultActionTrait;

	#[LiveProp]
	public ?Record $log;
	#[LiveProp]
	public string $status;


	public function __construct(
		private ManagerRegistry $registry,
		#[Autowire(param: 'dashboard_list_items')]
		private array           $classes
	) {}

	#[LiveAction]
	public function clearOne(
		#[LiveArg] string $status
	) {
		if (!$this->log) {
			return;
		}
		$class = $this->log::class;
		$repo  = $this->registry->getRepository($class);
		assert($repo instanceof RecordRepository);
		$repo->setStatus($this->log, $this->log->getDate(), $status, $this->status);
		$this->log = null;
	}
}
<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 10:54
 */
namespace BugCatcher\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use BugCatcher\Entity\Record;
use BugCatcher\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LogRecordSaveProcessor implements ProcessorInterface
{
	public function __construct(
		#[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
		private ProcessorInterface         $persistProcessor,
		private ValidatorInterface         $validator,
		private readonly ProjectRepository $projectRepo
	) {}


	public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []) {
		if (false === $data instanceof Record) {
			return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
		}

		$project = $this->projectRepo->findOneBy(["code" => $data->getProjectCode()]);
		if ($project === null) {
			throw new NotFoundHttpException("Project not found");
		}
		$data->setProject($project);
		$data->setHash($data->calculateHash());

		$this->validator->validate($data, ['groups' => "Default"]);

		return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
	}
}
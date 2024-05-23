<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 10:54
 */
namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\LogRecord;
use App\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LogRecordSaveProcessor implements ProcessorInterface {
	public function __construct(
		#[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
		private ProcessorInterface         $persistProcessor,
		private ValidatorInterface         $validator,
		private readonly ProjectRepository $projectRepo
	) {}


	public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []) {
		if (false === $data instanceof LogRecord) {
			return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
		}

		$project = $this->projectRepo->findOneBy(["code" => $data->getProjectCode()]);
		$data->setProject($project);

		$this->validator->validate($data, ['groups' => "Default"]);

		return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
	}
}
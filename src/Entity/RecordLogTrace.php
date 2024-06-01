<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:40
 */
namespace PhpSentinel\BugCatcher\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Api\Processor\LogRecordSaveProcessor;
use PhpSentinel\BugCatcher\Repository\RecordLogTraceRepository;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
	operations: [
		new Post(
			processor: LogRecordSaveProcessor::class
		),
	],
	denormalizationContext: ['groups' => ['record:write']],
	validationContext: ['groups' => ['api']],
)]
#[ORM\Entity(repositoryClass: RecordLogTraceRepository::class)]
class RecordLogTrace extends RecordLog {

	#[Groups(['record:write'])]
	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $stackTrace = null;

	public function getStackTrace(): ?string {
		return $this->stackTrace;
	}

	public function setStackTrace(?string $stackTrace): static {
		$this->stackTrace = $stackTrace;

		return $this;
	}

	function getComponentName(): string {
		return "LogList:RecordLog";
	}
}
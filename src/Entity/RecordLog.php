<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:28
 */
namespace PhpSentinel\BugCatcher\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use PhpSentinel\BugCatcher\Api\Processor\LogRecordSaveProcessor;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
#[ApiResource(
	operations: [
		new Post(
			processor: LogRecordSaveProcessor::class
		//			securityPostDenormalize: "is_granted('ROLE_CALCULATION_CREATE')",
		),
	],
	denormalizationContext: ['groups' => ['record:write']],
	validationContext: ['groups' => ['api']],
)]
#[ORM\Entity(repositoryClass: RecordLogRepository::class)]
class RecordLog extends Record {

	#[Groups(['record:write'])]
	#[ORM\Column]
	#[Assert\NotBlank(groups: ['api'])]
	protected ?int $level = null;

	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	#[ORM\Column(type: Types::TEXT)]
	protected ?string $message = null;

	#[Groups(['record:write'])]
	#[ORM\Column(length: 1500)]
	#[Assert\Length(max: 1500, groups: ['api'])]
	protected ?string $requestUri = null;


	public function getMessage(): ?string {
		return $this->message;
	}

	public function setMessage(string $message): static {
		$this->message = $message;

		return $this;
	}


	public function getLevel(): ?int {
		return $this->level;
	}

	public function setLevel(int $level): static {
		$this->level = $level;

		return $this;
	}

	public function getRequestUri(): ?string {
		return $this->requestUri;
	}

	public function setRequestUri(string $requestUri): static {
		$this->requestUri = $requestUri;

		return $this;
	}

	function getGroup(): ?string {
		return md5($this->project->getId()->toHex() .$this->message );
	}

	function getComponentName(): string {
		return "LogListRecordLog";
	}



}
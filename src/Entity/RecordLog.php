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
use PhpSentinel\BugCatcher\Api\Processor\LogRecordSaveProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
class RecordLog extends Record {

	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	protected ?int $level = null;

	#[Groups(['record:write'])]
	#[Assert\NotBlank(groups: ['api'])]
	protected ?string $message = null;

	#[Groups(['record:write'])]
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
		return md5($this->project->getId()->toHex() . $this->message);
	}

	function getComponentName(): string {
		return "LogList:RecordLog";
	}


}
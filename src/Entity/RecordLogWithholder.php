<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 10. 7. 2024
 * Time: 16:49
 */
namespace BugCatcher\Entity;

use BugCatcher\Validator\IsRegex;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


class RecordLogWithholder {

	protected ?Uuid $id = null;

	#[Assert\NotBlank()]
	#[Assert\Length(max: 750)]
	#[IsRegex()]
	protected ?string $regex = null;

	#[Assert\Length(max: 250)]
	protected ?string $name = null;

	#[Assert\NotBlank(groups: ['api'])]
	protected ?int $threshold = null;

	#[Assert\NotBlank(groups: ['api'])]
	protected ?int $thresholdInterval = null;

	#[Assert\NotBlank()]
	protected ?Project $project = null;

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getProject(): ?Project {
		return $this->project;
	}

	public function setProject(?Project $project): self {
		$this->project = $project;

		return $this;
	}

	public function getRegex(): ?string {
		return $this->regex;
	}

	public function setRegex(?string $regex): self {
		$this->regex = $regex;

		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(?string $name): self {
		$this->name = $name;

		return $this;
	}

	public function getThreshold(): ?int {
		return $this->threshold;
	}

	public function setThreshold(?int $threshold): self {
		$this->threshold = $threshold;

		return $this;
	}

	public function getThresholdInterval(): ?int {
		return $this->thresholdInterval;
	}

	public function setThresholdInterval(?int $thresholdInterval): self {
		$this->thresholdInterval = $thresholdInterval;

		return $this;
	}


}
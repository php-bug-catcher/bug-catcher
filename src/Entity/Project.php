<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project {
	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	private ?Uuid $id = null;

	#[ORM\Column(length: 255)]
	private ?string $code = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;


	public function __construct() {}

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getCode(): ?string {
		return $this->code;
	}

	public function setCode(string $code): static {
		$this->code = $code;

		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(string $name): static {
		$this->name = $name;

		return $this;
	}

}

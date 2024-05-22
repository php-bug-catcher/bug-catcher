<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 22. 5. 2024
 * Time: 16:45
 */
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

abstract class Record {

	#[ORM\Id]
	#[ORM\Column(type: UuidType::NAME, unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
	protected ?Uuid $id = null;

	#[ORM\Column(type: Types::DATETIME_MUTABLE)]
	protected ?\DateTimeInterface $date = null;

	#[ORM\ManyToOne()]
	#[ORM\JoinColumn(nullable: false)]
	protected ?Project $project = null;

	public function getId(): ?Uuid {
		return $this->id;
	}

	public function getDate(): ?\DateTimeInterface {
		return $this->date;
	}

	public function setDate(\DateTimeInterface $date): static {
		$this->date = $date;

		return $this;
	}

	public function getProject(): ?Project {
		return $this->project;
	}

	public function setProject(?Project $project): static {
		$this->project = $project;

		return $this;
	}
}
<?php

namespace App\Entity;

use App\Repository\PingRecordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PingRecordRepository::class)]
#[ORM\Index(name: 'full_idx', columns: ['project_id','date','status_code'])]
class PingRecord extends Record
{
    #[ORM\Column(length: 255)]
    private ?string $statusCode = null;

    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function setStatusCode(string $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }
}

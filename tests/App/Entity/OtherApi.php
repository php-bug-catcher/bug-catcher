<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use BugCatcher\Api\Processor\LogRecordSaveProcessor;
use BugCatcher\Entity\Record;
use BugCatcher\Tests\App\Repository\CronRecordRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/other-api',
            processor: LogRecordSaveProcessor::class
        ),
    ],
)]
class OtherApi
{
}

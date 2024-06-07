<?php

namespace PhpSentinel\BugCatcher\Entity;

use Doctrine\ORM\Mapping as ORM;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotifierRepository::class)]
class NotifierFavicon extends Notifier {

}

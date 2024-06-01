<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace PhpSentinel\BugCatcher\Twig\Components\Detail;

use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: '@BugCatcher/components/Detail/Title.html.twig')]
class Title {
	public Record $record;
}
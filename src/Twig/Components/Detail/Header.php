<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:06
 */
namespace PhpSentinel\BugCatcher\Twig\Components\Detail;

use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: '@BugCatcher/components/Detail/Header.html.twig')]
class Header {
	public Record $record;
}
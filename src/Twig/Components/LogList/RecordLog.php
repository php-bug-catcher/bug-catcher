<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 5. 10. 2023
 * Time: 20:38
 */
namespace PhpSentinel\BugCatcher\Twig\Components\LogList;

use App\Entity\Game\Game;
use App\Entity\Search\SearchTarget;
use App\Entity\Search\Speech;
use App\Repository\Search\SearchRepository;
use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;


#[AsTwigComponent(template: '@BugCatcher/components/LogList/RecordLog.html.twig')]
class RecordLog {
	public Record $log;
}
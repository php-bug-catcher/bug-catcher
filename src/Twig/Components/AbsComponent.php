<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 22. 5. 2024
 * Time: 17:33
 */
namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

abstract class AbsComponent {
	#[LiveProp]
	public Project $project;

	/**
	 * Whether the row this sits in has more to fit than the usual three cells.
	 *
	 * {@see StatusList} sets it for a project with performance switched on, because that row
	 * carries latency as well as errors and the twelve columns have to be shared out differently.
	 * Every status component accepts it so that the list stays configurable - most ignore it.
	 */
	#[LiveProp]
	public bool $dense = false;

}
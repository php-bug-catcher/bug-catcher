<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 24. 5. 2024
 * Time: 11:16
 */
namespace PhpSentinel\BugCatcher\Service\PingCollector;

use PhpSentinel\BugCatcher\Entity\Project;

interface PingCollectorInterface {
	public function ping(Project $project): string;
}
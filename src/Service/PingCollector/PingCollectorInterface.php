<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 24. 5. 2024
 * Time: 11:16
 */
namespace BugCatcher\Service\PingCollector;

use BugCatcher\Entity\Project;

interface PingCollectorInterface {
	public function ping(Project $project): string;
}
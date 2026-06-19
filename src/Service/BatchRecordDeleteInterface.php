<?php

namespace BugCatcher\Service;

use BugCatcher\Entity\Project;

interface BatchRecordDeleteInterface {

	/**
	 * @param string[]  $binaryIds raw binary UUIDs
	 * @param Project[] $projects
	 */
	public function deleteByIds(array $binaryIds, array $projects): void;
}

<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 22:28
 */
namespace BugCatcher\Service;

use BugCatcher\Entity\Project;
use JetBrains\PhpStorm\ArrayShape;
use BugCatcher\Entity\Notifier;
use BugCatcher\Enum\Importance;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DashboardImportance
{

	public array $importance = [];

	public function __construct(
		#[Autowire(param: 'kernel.cache_dir')]
		private readonly string $cacheDir
	) {}

    public function upgradeHigher(string $group, Project $project, Importance $importance, Notifier $notifier): void
    {
        $importances = $this->importance[$group] ?? null;
        if (!$importances) {
            $importances = $this->load($group);
        }
        $current = $importances[$project->getId()->toString()] ?? [
            "importance" => $importance,
            "notifier" => $notifier
        ];
        if ($importance->isHigherThan($current["importance"])) {
			$current = [
				"importance" => $importance,
				"notifier"   => $notifier,
			];
		}
        $this->importance[$group][$project->getId()->toString()] = $current;
	}

	public function save(string $group): void {
		$importance = $this->importance[$group]??null;
		if ($importance === null) {
			return;
		}
		$group = substr(md5($group), 0, 8);
		file_put_contents($this->cacheDir . "/importance-$group.txt", serialize($importance));
	}

    #[ArrayShape([
        "string" => [
            'importance' => "BugCatcher\Enum\Importance",
            'notifier' => "BugCatcher\Entity\Notifier"
        ]
    ])]
    public function load(
        string $group,
    ): ?array
    {
		$group = substr(md5($group), 0, 8);
		if (!file_exists($this->cacheDir . "/importance-$group.txt")) {
            return [];
		}

		return unserialize(file_get_contents($this->cacheDir . "/importance-$group.txt"));
	}
}
<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 4. 10. 2024
 * Time: 15:06
 */

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Entity\User;
use BugCatcher\Enum\Importance;
use JetBrains\PhpStorm\ArrayShape;

trait DashboardImportance
{
    #[ArrayShape(['importance' => "BugCatcher\Enum\Importance", 'notifier' => "BugCatcher\Entity\Notifier"])]
    public function getMaxImportance($class): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        /** @var NotifierFavicon $notifier */
        $projects = $this->importance->load($class);
        if ($user) {
            $tmpProjects = $projects;
            $projects = [];
            foreach ($tmpProjects as $projectId => $data) {
                foreach ($user->getProjects() as $project) {
                    if ($project->getId()->toString() == $projectId) {
                        $projects[$projectId] = array_values($data);
                    }
                }
            }
        }
        $maxImportance = Importance::min();
        $maxNotifier = null;
        foreach ($projects as [$importance, $notifier]) {
            if (!$importance) {
                continue;
            }
            if ($importance->isHigherThan($maxImportance)) {
                $maxImportance = $importance;
                $maxNotifier = $notifier;
            }
        }
        return [$maxImportance, $maxNotifier];
    }
}
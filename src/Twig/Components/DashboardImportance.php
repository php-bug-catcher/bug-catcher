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
    public function getMaxImportance(): array
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        /** @var NotifierFavicon $notifier */
        $projects = $this->importance->load(NotifierFavicon::class);
        if ($user) {
            $tmpProjects = $projects;
            $projects = [];
            foreach ($tmpProjects as $projectId => $data) {
                foreach ($user->getProjects() as $project) {
                    if ($project->getId()->toString() == $projectId) {
                        $projects[$projectId] = $data;
                    }
                }
            }
        }
        $maxImportance = Importance::min();
        $maxNotifier = null;
        foreach ($projects as [$importance, $notifier]) {
            if ($importance->isHigherThan($maxImportance)) {
                $maxImportance = $importance;
                $maxNotifier = $notifier;
            }
        }
        return [$maxImportance, $maxNotifier];
    }
}
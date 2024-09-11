<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 11. 9. 2024
 * Time: 20:32
 */

namespace BugCatcher\Tests\App\Service;

use BugCatcher\Entity\Project;
use BugCatcher\Service\PingCollector\PingCollectorInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class ThrowCollector implements PingCollectorInterface
{

    public function ping(Project $project): string
    {
        throw new Exception("This collector always throws exception");
    }
}
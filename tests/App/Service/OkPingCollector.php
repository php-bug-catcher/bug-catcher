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
use Symfony\Component\HttpFoundation\Response;

class OkPingCollector implements PingCollectorInterface
{

    public function ping(Project $project): string
    {
        return Response::HTTP_OK;
    }
}
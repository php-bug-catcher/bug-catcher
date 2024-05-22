<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 22. 5. 2024
 * Time: 17:33
 */
namespace App\Twig\Components;

use App\Entity\Project;

abstract class AbsComponent {

	public Project $project;

}
<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

class ProjectStatusTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	/**
	 * The project name on the dashboard is the way to that project's charts - the row has no
	 * other control that could carry the link, and wrapping the whole row in an anchor would
	 * swallow the clicks of whatever else `status_list_components` was configured with.
	 */
	public function testTheNameLinksToThePerformancePageOfThatProject(): void
	{
		$project = ProjectFactory::createOne(['pingCollector' => 'none', 'name' => 'Checkout'])->_real();

		$html = (string)$this->renderTwigComponent('ProjectStatus', ['project' => $project]);

		$this->assertStringContainsString('href="/performance/' . $project->getId() . '"', $html);
		$this->assertStringContainsString('Checkout', $html);
	}
}

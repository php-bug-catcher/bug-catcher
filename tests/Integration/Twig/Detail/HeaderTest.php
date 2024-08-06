<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 13:01
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig\Detail;

use PhpSentinel\BugCatcher\Tests\App\Factory\RecordLogFactory;
use PhpSentinel\BugCatcher\Tests\App\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HeaderTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;

	public function testHeader(): void {
		//uri 160 lenght long
		$uri    = "https://www.google.com/search?q=google+search+engine+optimization+seo+search+engine+marketing+sem+advertising+ppc+google+ads+google+adwords+google+my+business+google+maps+google+search+console+google+analytics+google+tag+manager+google+optimize+google+data+studio+google+surveys+google+ads+manager+google+ad+manager+google+ad";
		$record = RecordLogFactory::createOne([
			"requestUri" => $uri,
		]);

		$rendered = $this->renderTwigComponent('Header', ['record' => $record]);
		$this->assertSame(mb_substr($uri, 0, 147) . "...", $rendered->crawler()->filter('.fs-4')->text());
		$this->assertSame($uri, $rendered->crawler()->filter('a')->attr('href'));
	}
}
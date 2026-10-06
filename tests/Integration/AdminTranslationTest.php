<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration;

use BugCatcher\Tests\App\KernelTestCase;
use Symfony\Component\Translation\Translator;

/**
 * The administration speaks whatever the installation speaks.
 *
 * The sibling of {@see Perf\PerfTranslationTest}, and for the same reason: the failure is silent.
 * EasyAdmin's default translation domain is `messages`, this bundle's catalogue is `BugCatcher`,
 * and a label it cannot find falls back to its own key - so a missing entry renders perfectly, in
 * English, for ever. Nothing fails, nothing logs, and the only way to find out is to look.
 *
 * The strings here are the ones EasyAdmin actually asks for, read off the rendered forms rather
 * than worked out from the property names: `dbConnection` becomes `Db Connection` and `url`
 * becomes `URL`, and guessing which is which is how a catalogue fills up with entries that never
 * match anything.
 */
class AdminTranslationTest extends KernelTestCase
{
	/** Every label and help text the administration asks for and this bundle has a word for. */
	private const array TRANSLATED = [
		// the menu
		'Dashboard',
		'Users',
		'Projects',
		'Withholders',
		'Notifiers',
		'Sound',
		'Email',
		'Change password',
		// the project form
		'Code',
		'Name',
		'Enabled',
		'Performance',
		'Show latency on the dashboard row. Needs the collector shipping buckets for this project.',
		'Workload',
		'Web — people are waiting',
		'Worker — cron jobs, messenger, loops',
		"A worker row drops Apdex and p95, which measure how long somebody waited, and shows a regression count against this project's own baseline plus the rate it is running at.",
		'Ping Collector',
		'Db Connection',
		// the user form
		'Fullname',
		'Roles',
		'New password',
		// the notifier form
		'Minimal Importance',
		'Threshold',
		'Component',
		'Delay',
		'Delay Interval',
		'Repeat',
		'Repeat Interval',
		'Clear At',
		'Clear Interval',
		'Title',
		'Description',
		// the withholder form
		'Project',
		'Threshold Interval',
		'The number of times the regex must be matched within the threshold interval to trigger a notification',
		'The time interval in seconds over which the threshold is calculated',
	];

	/**
	 * Labels deliberately absent from the catalogue because the Slovak is the English.
	 *
	 * Listed rather than ignored: an entry added for one of these is not a translation, it is a
	 * chance to misspell a word that was already right.
	 */
	private const array UNTRANSLATED = [
		'URL',
		'Regex',
		'Favicon',
	];

	public function testEveryAdminLabelHasASlovakWord(): void
	{
		$translator = $this->translator();

		foreach (self::TRANSLATED as $source) {
			$this->assertNotSame(
				$source,
				$translator->trans($source, [], 'BugCatcher', 'sk'),
				sprintf('"%s" falls back to its own key, so the administration renders it in English', $source),
			);
		}
	}

	public function testTheLabelsThatAreTheSameInBothLanguagesAreLeftAlone(): void
	{
		$translator = $this->translator();

		foreach (self::UNTRANSLATED as $source) {
			$this->assertSame($source, $translator->trans($source, [], 'BugCatcher', 'sk'));
		}
	}

	/**
	 * And the domain itself, which is the part that makes any of the above reachable: with
	 * EasyAdmin left on `messages` the catalogue is never consulted at all.
	 */
	public function testTheDashboardReadsThisBundlesCatalogue(): void
	{
		$dashboard = self::getContainer()
			->get(\BugCatcher\Controller\Admin\DashboardController::class)
			->configureDashboard()
			->getAsDto();

		$this->assertSame('BugCatcher', $dashboard->getTranslationDomain());
	}

	private function translator(): Translator
	{
		$translator = self::getContainer()->get('translator');
		$this->assertInstanceOf(Translator::class, $translator);

		return $translator;
	}
}

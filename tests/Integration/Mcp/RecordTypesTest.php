<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordPing;
use BugCatcher\Mcp\RecordTypes;
use BugCatcher\Tests\App\Entity\RecordCron;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Zenstruck\Foundry\Test\Factories;

class RecordTypesTest extends KernelTestCase {
	use Factories;

	private EntityManagerInterface $em;

	protected function setUp(): void {
		self::bootKernel();
		$this->em = self::getContainer()->get(EntityManagerInterface::class);
	}

	/**
	 * A configured class brings its subclasses, the way `INSTANCE OF RecordLog` would. Otherwise every
	 * application would have to remember that a traced log is a second discriminator value.
	 */
	public function testASubclassOfAConfiguredTypeComesAlong() {
		$types = new RecordTypes($this->em, [RecordLog::class]);

		$this->assertEqualsCanonicalizing(['log', 'trace-log'], $types->discriminators());
	}

	public function testACustomTypeIsResolvedToItsDiscriminatorValue() {
		$types = new RecordTypes($this->em, [RecordLog::class, RecordCron::class]);

		$this->assertEqualsCanonicalizing(['log', 'trace-log', 'cron'], $types->discriminators());
	}

	/**
	 * Silence here would leave the type simply never found, and the reader would be left hunting for a
	 * query bug that is a typo in a YAML file.
	 */
	public function testAClassOutsideTheDiscriminatorMapIsRefusedLoudly() {
		$types = new RecordTypes($this->em, ['App\Entity\NotMapped']);

		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('App\Entity\NotMapped');
		$types->discriminators();
	}

	public function testTheMessageOfARefusedClassNamesTheTypesThatDoExist() {
		$types = new RecordTypes($this->em, ['App\Entity\NotMapped']);

		try {
			$types->discriminators();
			$this->fail('a class outside the map should be refused');
		} catch (LogicException $e) {
			$this->assertStringContainsString(RecordLog::class, $e->getMessage());
			$this->assertStringContainsString(RecordPing::class, $e->getMessage());
		}
	}

	public function testARecordOfAConfiguredTypeIsAllowed() {
		$types  = new RecordTypes($this->em, [RecordLog::class]);
		$traced = RecordLogTraceFactory::createOne([
			'project' => ProjectFactory::createOne(['code' => 'shop', 'enabled' => true]),
		])->_real();

		$this->assertTrue($types->allows($traced));
	}

	public function testARecordOfAnUnconfiguredTypeIsNotAllowed() {
		$types = new RecordTypes($this->em, [RecordLog::class]);
		$ping  = RecordPingFactory::createOne([
			'project' => ProjectFactory::createOne(['code' => 'shop', 'enabled' => true]),
		])->_real();

		$this->assertInstanceOf(Record::class, $ping);
		$this->assertFalse($types->allows($ping));
	}
}

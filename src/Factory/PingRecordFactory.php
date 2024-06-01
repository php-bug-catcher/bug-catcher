<?php

namespace PhpSentinel\BugCatcher\Factory;

use PhpSentinel\BugCatcher\Entity\RecordPing;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<RecordPing>
 *
 * @method        RecordPing|Proxy                     create(array|callable $attributes = [])
 * @method static RecordPing|Proxy                     createOne(array $attributes = [])
 * @method static RecordPing|Proxy                     find(object|array|mixed $criteria)
 * @method static RecordPing|Proxy                     findOrCreate(array $attributes)
 * @method static RecordPing|Proxy                     first(string $sortedField = 'id')
 * @method static RecordPing|Proxy                     last(string $sortedField = 'id')
 * @method static RecordPing|Proxy                     random(array $attributes = [])
 * @method static RecordPing|Proxy                     randomOrCreate(array $attributes = [])
 * @method static RecordPingRepository|RepositoryProxy repository()
 * @method static RecordPing[]|Proxy[]                 all()
 * @method static RecordPing[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static RecordPing[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static RecordPing[]|Proxy[]                 findBy(array $attributes)
 * @method static RecordPing[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static RecordPing[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class PingRecordFactory extends ModelFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
	 *
	 * @todo add your default values here
	 */
	protected function getDefaults(): array {
		return [
			'date'    => self::faker()->dateTimeBetween("-10 days"),
			'project' => ProjectFactory::random(),
			'statusCode' => self::faker()->randomElement([
				Response::HTTP_OK,
				Response::HTTP_BAD_GATEWAY,
				Response::HTTP_FOUND,
				Response::HTTP_FORBIDDEN,
				Response::HTTP_INTERNAL_SERVER_ERROR,
			]),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): self {
		return $this// ->afterInstantiate(function(PingRecord $pingRecord): void {})
			;
	}

	protected static function getClass(): string {
		return RecordPing::class;
	}
}

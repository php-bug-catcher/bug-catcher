<?php

namespace App\Factory;

use App\Entity\PingRecord;
use App\Repository\PingRecordRepository;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<PingRecord>
 *
 * @method        PingRecord|Proxy                     create(array|callable $attributes = [])
 * @method static PingRecord|Proxy                     createOne(array $attributes = [])
 * @method static PingRecord|Proxy                     find(object|array|mixed $criteria)
 * @method static PingRecord|Proxy                     findOrCreate(array $attributes)
 * @method static PingRecord|Proxy                     first(string $sortedField = 'id')
 * @method static PingRecord|Proxy                     last(string $sortedField = 'id')
 * @method static PingRecord|Proxy                     random(array $attributes = [])
 * @method static PingRecord|Proxy                     randomOrCreate(array $attributes = [])
 * @method static PingRecordRepository|RepositoryProxy repository()
 * @method static PingRecord[]|Proxy[]                 all()
 * @method static PingRecord[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static PingRecord[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static PingRecord[]|Proxy[]                 findBy(array $attributes)
 * @method static PingRecord[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static PingRecord[]|Proxy[]                 randomSet(int $number, array $attributes = [])
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
			'date' => self::faker()->dateTimeBetween("-10 days"),
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
		return PingRecord::class;
	}
}

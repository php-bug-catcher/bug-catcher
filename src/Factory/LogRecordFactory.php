<?php

namespace App\Factory;

use App\Entity\LogRecord;
use App\Repository\LogRecordRepository;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Zenstruck\Foundry\RepositoryProxy;

/**
 * @extends ModelFactory<LogRecord>
 *
 * @method        LogRecord|Proxy                     create(array|callable $attributes = [])
 * @method static LogRecord|Proxy                     createOne(array $attributes = [])
 * @method static LogRecord|Proxy                     find(object|array|mixed $criteria)
 * @method static LogRecord|Proxy                     findOrCreate(array $attributes)
 * @method static LogRecord|Proxy                     first(string $sortedField = 'id')
 * @method static LogRecord|Proxy                     last(string $sortedField = 'id')
 * @method static LogRecord|Proxy                     random(array $attributes = [])
 * @method static LogRecord|Proxy                     randomOrCreate(array $attributes = [])
 * @method static LogRecordRepository|RepositoryProxy repository()
 * @method static LogRecord[]|Proxy[]                 all()
 * @method static LogRecord[]|Proxy[]                 createMany(int $number, array|callable $attributes = [])
 * @method static LogRecord[]|Proxy[]                 createSequence(iterable|callable $sequence)
 * @method static LogRecord[]|Proxy[]                 findBy(array $attributes)
 * @method static LogRecord[]|Proxy[]                 randomRange(int $min, int $max, array $attributes = [])
 * @method static LogRecord[]|Proxy[]                 randomSet(int $number, array $attributes = [])
 */
final class LogRecordFactory extends ModelFactory
{
	private $messages = [];
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
		for($i=0;$i<50;$i++){
			$this->messages[] = self::faker()->text();
		}
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function getDefaults(): array
    {
        return [
            'checked' => self::faker()->boolean(),
            'date' => self::faker()->dateTimeBetween("-10 days"),
            'level' => self::faker()->randomElement([500,200,100,50]),
            'message' => self::faker()->randomElement($this->messages),
            'project' => ProjectFactory::random(),
            'requestUri' => self::faker()->text(255),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): self
    {
        return $this
            // ->afterInstantiate(function(LogRecord $logRecord): void {})
        ;
    }

    protected static function getClass(): string
    {
        return LogRecord::class;
    }
}

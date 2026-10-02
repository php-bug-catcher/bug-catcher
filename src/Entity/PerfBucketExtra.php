<?php

declare(strict_types=1);

namespace BugCatcher\Entity;

/**
 * One extra metric of one {@see PerfBucket}: whatever the monitored application merged into
 * `$GLOBALS['_bcperf_extra']` - SQL query count and time for a Doctrine application, anything
 * numeric for anyone else.
 *
 * A table of its own rather than a column on the bucket, because the names are not known until a
 * row arrives: MySQL before 5.7 has no JSON type, and even where it does, adding a value to a
 * dynamically named key means building JSON paths out of client-controlled strings. Here the name
 * is a parameter, addition is `value = value + ?` against the primary key, and a dashboard can
 * aggregate the metric in SQL.
 *
 * The identifier is `(bucket, name)` - there is nothing else a row could be identified by, and a
 * surrogate key would only buy a second index.
 */
final class PerfBucketExtra
{
	public function __construct(
		private PerfBucket $bucket,
		private string $name,
		private float $value,
	) {
	}

	public function getBucket(): PerfBucket
	{
		return $this->bucket;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getValue(): float
	{
		return $this->value;
	}
}

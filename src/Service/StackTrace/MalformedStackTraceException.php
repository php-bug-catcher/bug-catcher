<?php

namespace BugCatcher\Service\StackTrace;

use RuntimeException;

/**
 * The stored stack trace payload could not be restored into code frames.
 *
 * Carries no detail about why on purpose: the payload comes from a client application and the
 * reader can do nothing with the difference between "not serialized at all" and "serialized
 * something else".
 */
final class MalformedStackTraceException extends RuntimeException
{
	public function __construct(string $reason) {
		parent::__construct('Unable to unserialize stacktrace: ' . $reason);
	}
}

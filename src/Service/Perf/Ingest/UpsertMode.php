<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Ingest;

/**
 * What a second write to the same {@see \BugCatcher\Entity\PerfBucket} row means.
 *
 * The difference is whether the caller is contributing to a bucket or recomputing it, and it is
 * the caller that knows: ingest only ever has a part of a minute in its hands, while the roll-up
 * builds a whole hour out of every minute in it.
 */
enum UpsertMode
{
	/**
	 * Add to what is there. The hook writes its line in shutdown, so a request that started inside
	 * a minute can be shipped after that minute already was; adding is what lets the late line
	 * land in its own bucket. Maxima take `GREATEST`, since each batch saw only some of the
	 * requests.
	 */
	case Add;

	/**
	 * Overwrite what is there. A roll-up reads every source row of the target bucket each time it
	 * runs, so what it writes is the answer rather than a contribution to one - which is also what
	 * makes a second run over the same window a no-op instead of a doubling. Maxima are
	 * authoritative too, downwards included.
	 */
	case Replace;
}

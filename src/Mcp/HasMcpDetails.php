<?php

namespace BugCatcher\Mcp;

/**
 * A record type with fields of its own worth reading.
 *
 * `get_record_detail` reports the same shape for every record, because the common shape is all
 * {@see \BugCatcher\Entity\Record} guarantees. A custom subtype usually holds the part that actually
 * explains the failure - which command ran, how long it took, how long it was supposed to take.
 * Implement this and those fields arrive under the `details` key.
 *
 * Flat and already printable: the caller reads JSON, so scalars, and dates formatted rather than
 * handed over as objects.
 */
interface HasMcpDetails
{
	/**
	 * @return array<string, scalar|null>
	 */
	public function getMcpDetails(): array;
}

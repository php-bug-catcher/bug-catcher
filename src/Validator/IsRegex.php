<?php

namespace BugCatcher\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_METHOD|Attribute::IS_REPEATABLE)]
class IsRegex extends Constraint {
	/*
	 * Any public properties become valid options for the annotation.
	 * Then, use these in your validator class.
	 */
	public string $message = 'The value is not valid regex.';
}

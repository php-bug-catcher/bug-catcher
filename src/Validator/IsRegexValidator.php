<?php

namespace BugCatcher\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class IsRegexValidator extends ConstraintValidator
{
	public function validate(mixed $value, Constraint $constraint): void {
		/* @var IsRegex $constraint */

		if (null === $value || '' === $value) {
			return;
		}

		assert($constraint instanceof IsRegex);

		if (false === @preg_match($value, '')) {
			$this->context->buildViolation($constraint->message)
				->setParameter('{{ value }}', $value)
				->addViolation();
		}
	}
}

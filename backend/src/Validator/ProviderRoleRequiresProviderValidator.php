<?php

namespace App\Validator;

use App\Entity\User;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ProviderRoleRequiresProviderValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ProviderRoleRequiresProvider) {
            throw new UnexpectedTypeException($constraint, ProviderRoleRequiresProvider::class);
        }

        if (!$value instanceof User) {
            return;
        }

        $roles = $value->getRoles();
        if (in_array('R_PROVIDER', $roles, true) && null === $value->getProvider()) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ role }}', 'R_PROVIDER')
                ->atPath('provider')
                ->addViolation();
        }
    }
}


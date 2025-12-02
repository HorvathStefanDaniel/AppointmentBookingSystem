<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ProviderRoleRequiresProvider extends Constraint
{
    public string $message = 'Users with role "{{ role }}" must be linked to a provider.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}


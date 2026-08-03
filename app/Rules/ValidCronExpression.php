<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CronExpression::isValidExpression($value)) {
            $fail('The :attribute must be a valid cron expression (e.g. "*/5 * * * *").');
        }
    }
}

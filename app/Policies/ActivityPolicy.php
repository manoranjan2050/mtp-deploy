<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view activity log');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('view activity log');
    }
}

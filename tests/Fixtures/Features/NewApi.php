<?php

namespace AmjadIqbal\PennantDashboard\Tests\Fixtures\Features;

use AmjadIqbal\PennantDashboard\Concerns\HasRollout;
use AmjadIqbal\PennantDashboard\Tests\Fixtures\User;

class NewApi
{
    use HasRollout;

    public function resolve(User $user): bool
    {
        return $this->rollout($user, default: false);
    }
}

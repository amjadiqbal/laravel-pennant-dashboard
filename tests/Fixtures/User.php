<?php

namespace AmjadIqbal\PennantDashboard\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Pennant\Concerns\HasFeatures;

class User extends Authenticatable
{
    use HasFeatures;

    protected $table = 'users';

    protected $guarded = [];
}

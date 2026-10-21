<?php

namespace AmjadIqbal\PennantDashboard\Tests\Fixtures\Features;

class PlainBanner
{
    public $name = 'plain-banner';

    public function resolve(mixed $scope): bool
    {
        return true;
    }
}

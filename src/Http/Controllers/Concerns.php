<?php

declare(strict_types=1);

namespace AmjadIqbal\PennantDashboard\Http\Controllers;

use AmjadIqbal\PennantDashboard\Support\StoreInspector;
use Illuminate\Http\Request;

trait Concerns
{
    protected function store(Request $request): StoreInspector
    {
        $name = (string) $request->input('store', StoreInspector::default());

        abort_unless(in_array($name, StoreInspector::names(), true), 404, "Pennant store [{$name}] is not configured.");

        return new StoreInspector($name);
    }
}

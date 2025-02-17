<?php

namespace WPSC\Services;

use Illuminate\Support\Facades\View;

class ComponentService {
    public function componentExists(string $component) : bool {
        $factory = app(ComponentFactory::class);
        try {
            dd($component);
            $factory->resolveComponentClass($component);
            // Component exists
        } catch (\InvalidArgumentException $e) {
            // Component doesn't exist
        }
// Check if a view exists
        dd(View::exists($component));
        if (View::exists('components.button')) {
            // The view component exists
        } else {
            // The view component doesn't exist
        }

    }
}

<?php

namespace Crumbls\WordPressRenderer\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;

abstract class AbstractComponent extends Component {

    protected static $classInitializers;

    /**
     * Set the extra attributes that the component should make available.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function withAttributes(array $attributes)
    {
        // Convert all keys to snake case
        $attributes = array_combine(
            array_map(fn($key) => \Illuminate\Support\Str::camel($key), array_keys($attributes)),
            array_values($attributes)
        );

        if (config('app.debug')) {
            $parameters = static::extractConstructorParameters();

            if ($temp = array_diff(array_keys($attributes), $parameters)) {
                dd(implode(PHP_EOL,array_map(function($e) { return 'public $'.$e.' = null,'; }, $temp)), static::class);
                $key = reset($temp);
                throw new \Exception("Define '{$key}' in the constructor of " . static::class);
            }

        }

        return parent::withAttributes($attributes);
    }


    /**
     * Get the data that should be supplied to the view.
     *
     * @author Freek Van der Herten
     * @author Brent Roose
     *
     * @return array
     */
    public function data()
    {
        $this->attributes = $this->attributes ?: $this->newAttributeBag();

        $this->attributes['element_classes'] = $this->generateClasses();

        return array_merge($this->extractPublicProperties(), $this->extractPublicMethods());
    }

    // Inside Illuminate\Database\Eloquent\Concerns\HasAttributes trait
    protected function generateClasses()
    {
        $class = static::class;

        $generated = [];

        $return = [];

        static::$classInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'generateClasses'.class_basename($trait);

            if (method_exists($class, $method) && ! in_array($method, $generated)) {
                $ret = $this->$method();
                if (is_array($ret)) {
                    $return = $return + $ret;
                } else if (is_string($ret)) {
                    $return = $return + explode(' ', $ret);
                }
                $generated[] = $method;
            }

            if (method_exists($class, $method = 'generateClasses'.class_basename($trait))) {
                static::$classInitializers[$class][] = $method;

                static::$classInitializers[$class] = array_unique(
                    static::$classInitializers[$class]
                );
            }
        }

        $return = array_unique($return);

        return $return;
    }

    public function render() {
        $view = 'components.'.Str::kebab(class_basename(get_called_class()));

        $data = $this->data();

        $temp = $data['attributes']->all();

        unset($data['attributes']);

        $data = array_merge($data, $temp);

        return view($view, $data);
    }
}

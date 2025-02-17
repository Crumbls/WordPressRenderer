# WordPress Content Rendering

Parse WordPress post_content to render shortcodes in Laravel, giving us an option for to use Laravel as a front end service.

## Why does this package exist?

We have a few clients who love the administrative side of WordPress, but find the front end to be full of bloat.  One
wanted a way to render any shortcode on a Laravel frontend using WP as the backend.  Corcel is capable, but every shortcode
must be registered in the configuration.  Our big issue comes with parsing content for some pages builders ( looking at you Divi )
where some versions don't always use a key="value" wrapper.  We wanted to specifically convert Divi into a Tailwind page, so
this is our first go at it.

## Installation

You can install the package via composer:

```bash
composer require crumbls/wordpress-renderer
```

## Usage

```php
$record = Page::inRandomOrder()
            ->take(1)
            ->status('publish')
            ->first();
$record->post_content =  app(ShortcodeConverterService::class)->convert($record->post_content);
echo Blade::render($record->post_content);
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

<?php

declare(strict_types=1);

use Kirby\Cms\Page;
use Kirby\Http\Response;
use TimFeskens\Seo\Endpoints;
use TimFeskens\Seo\Integrations;
use TimFeskens\Seo\Preview;
use TimFeskens\Seo\Seo;

// Manual installation is supported as well as Composer.
spl_autoload_register(static function (string $class): void {
    $prefix = 'TimFeskens\\Seo\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

Kirby::plugin('timfeskens/seo', [
    'options' => [
        'canonicalBase' => null,
        'locale' => 'en_US',
        'description' => '',
        'noindex' => false,
        'sitemap' => true,
        'robots' => true,
        'schema' => null,
        'include' => null,
        'cache' => ['active' => true],
        'llms' => ['enabled' => false, 'include' => null, 'markdown' => null],
        'ai' => ['enabled' => false, 'source' => null, 'provider' => null],
        'indexnow' => ['enabled' => false, 'key' => null],
    ],
    'snippets' => ['timfeskens-seo/meta' => __DIR__ . '/snippets/meta.php'],
    'blueprints' => [
        'tabs/seo-site' => __DIR__ . '/blueprints/tabs/site.yml',
        'tabs/seo-page' => __DIR__ . '/blueprints/tabs/page.yml',
        'fields/seo-robots' => __DIR__ . '/blueprints/fields/robots.yml',
        'sections/seo-settings' => __DIR__ . '/blueprints/sections/settings.yml',
    ],
    'pageMethods' => [
        'seo' => function (): array { return Seo::metadata($this); },
    ],
    'fields' => [
        'timfeskens-seo-text' => [
            'extends' => 'text',
            'save' => function ($value) { return Preview::stored($this->model(), $this->name(), $value); },
            'computed' => [
                'default' => function () { return Preview::default($this->model(), $this->name()); },
                'placeholder' => function () { return Preview::default($this->model(), $this->name()); },
                'value' => function () { return $this->value !== '' && $this->value !== null ? $this->value : Preview::default($this->model(), $this->name()); },
                'help' => function () { return $this->help ? $this->kirby()->kirbytext($this->help) : null; },
            ],
        ],
        'timfeskens-seo-textarea' => [
            'extends' => 'textarea',
            'save' => function ($value) { return Preview::stored($this->model(), $this->name(), $value); },
            'computed' => [
                'default' => function () { return Preview::default($this->model(), $this->name()); },
                'placeholder' => function () { return Preview::default($this->model(), $this->name()); },
                'value' => function () { return $this->value !== '' && $this->value !== null ? $this->value : Preview::default($this->model(), $this->name()); },
            ],
        ],
        'timfeskens-seo-select' => [
            'extends' => 'select',
            // Kirby's component inheritance resolves one level only; select itself extends radio.
            'mixins' => ['options'],
            'computed' => [
                'default' => function () { return $this->sanitizeOption($this->default); },
                'value' => function () { return $this->sanitizeOption($this->value) ?? $this->default(); },
            ],
        ],
        'timfeskens-seo-toggle' => [
            'extends' => 'toggle',
            'methods' => ['emptyValue' => function () { return null; }],
            'computed' => [
                'value' => function () { return $this->value !== '' && $this->value !== null ? $this->toBool($this->value) : $this->default(); },
            ],
        ],
        'timfeskens-seo-preview' => [
            'save' => false,
            'computed' => [
                'preview' => function (): array {
                    $model = $this->model();
                    return Preview::metadata($model, []);
                },
                'ai' => fn (): bool => Seo::option('ai.enabled', false) === true,
            ],
            'api' => function (): array {
                $model = $this->model();
                return [
                    [
                        'pattern' => 'preview',
                        'method' => 'POST',
                        'action' => function () use ($model): array {
                            $data = kirby()->request()->get('content', []);
                            if (!is_array($data)) {
                                $data = [];
                            }
                            return Preview::metadata($model, $data);
                        },
                    ],
                    [
                        'pattern' => 'suggest',
                        'method' => 'POST',
                        'action' => function () use ($model): array {
                            if (!$model instanceof Page) {
                                throw new RuntimeException('Suggestions are available for pages only.');
                            }
                            return Integrations::suggest($model);
                        },
                    ],
                ];
            },
        ],
    ],
    'routes' => function (): array {
        $routes = [];
        if (Seo::option('sitemap', true)) {
            $routes[] = ['pattern' => 'sitemap.xml', 'action' => fn () => Endpoints::sitemap()];
            $routes[] = ['pattern' => 'sitemap', 'action' => fn () => go('sitemap.xml', 301)];
        }
        if (Seo::option('robots', true)) {
            $routes[] = ['pattern' => 'robots.txt', 'action' => fn () => Endpoints::robots()];
        }
        if (Seo::option('llms.enabled', false)) {
            $routes[] = ['pattern' => 'llms.txt', 'action' => fn () => Endpoints::llms()];
            $routes[] = ['pattern' => 'seo/markdown/(:all).md', 'action' => fn ($id) => Endpoints::markdown($id)];
        }
        if (Integrations::indexNowEnabled()) {
            $routes[] = ['pattern' => Integrations::key() . '.txt', 'action' => fn () => new Response(Integrations::key(), 'text/plain')];
        }
        return $routes;
    },
    'hooks' => [
        'page.update:after' => function (Page $newPage, Page $oldPage): void {
            Integrations::enqueue($oldPage);
            Integrations::enqueue($newPage);
        },
        'page.changeStatus:after' => function (Page $newPage, Page $oldPage): void {
            Integrations::enqueue($oldPage);
            Integrations::enqueue($newPage);
        },
        'page.changeSlug:after' => function (Page $newPage, Page $oldPage): void {
            Integrations::enqueue($oldPage);
            Integrations::enqueue($newPage);
        },
        'page.delete:before' => function (Page $page): void { Integrations::enqueue($page); },
        'route:after' => function ($route, $path, $method, $result, $final): void {
            if (Seo::blocked()) {
                kirby()->response()->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
            }
        },
    ],
]);

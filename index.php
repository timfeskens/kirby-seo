<?php

declare(strict_types=1);

use Kirby\Cms\Page;
use Kirby\Http\Response;
use TimFeskens\Seo\Endpoints;
use TimFeskens\Seo\Integrations;
use TimFeskens\Seo\Preview;
use TimFeskens\Seo\Seo;
use TimFeskens\Seo\Analytics;
use TimFeskens\Seo\SearchConsole;

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
        'canonicalUrl' => null,
        'locale' => 'en_US',
        'description' => '',
        'sitemap' => true,
        'robots' => true,
        'schema' => null,
        'include' => null,
        'cache' => ['active' => true],
        'llms' => ['enabled' => false, 'include' => null, 'markdown' => null],
        'ai' => ['enabled' => false, 'source' => null, 'provider' => null],
        'indexnow' => ['enabled' => false, 'key' => null],
        'searchConsole' => ['enabled' => false, 'credentials' => null, 'tokenPath' => null, 'property' => null],
        'ahrefs' => ['enabled' => false, 'apiKey' => null, 'mode' => 'free'],
    ],
    'snippets' => ['timfeskens-seo/meta' => __DIR__ . '/snippets/meta.php'],
    'blueprints' => [
        'tabs/seo-site' => __DIR__ . '/blueprints/tabs/site.yml',
        'tabs/seo-page' => __DIR__ . '/blueprints/tabs/page.yml',
        'fields/seo-robots' => __DIR__ . '/blueprints/fields/robots.yml',
        'fields/seo-robots-global' => __DIR__ . '/blueprints/fields/robots-global.yml',
        'sections/seo-search' => __DIR__ . '/blueprints/sections/search.yml',
        'sections/seo-social' => __DIR__ . '/blueprints/sections/social.yml',
        'sections/seo-robots' => __DIR__ . '/blueprints/sections/robots.yml',
        'sections/seo-robots-site' => __DIR__ . '/blueprints/sections/robots-site.yml',
        'sections/seo-profiles' => __DIR__ . '/blueprints/sections/profiles.yml',
        'sections/seo-previews' => __DIR__ . '/blueprints/sections/previews.yml',
    ],
    'pageMethods' => [
        'seo' => function (): array { return Seo::metadata($this); },
    ],
    'fields' => [
        'timfeskens-seo-template' => [
            'extends' => 'text',
            'save' => function ($value) { return Preview::stored($this->model(), $this->name(), $value); },
            'computed' => [
                'default' => function () { return Preview::default($this->model(), $this->name()); },
                'placeholder' => function () { return Preview::default($this->model(), $this->name()); },
                'value' => function () { return $this->value !== '' && $this->value !== null ? $this->value : Preview::default($this->model(), $this->name()); },
            ],
        ],
        'timfeskens-seo-analytics' => [
            'save' => false,
            'computed' => [
                'providers' => fn (): array => Analytics::providers(),
            ],
            'api' => function (): array {
                $model = $this->model();
                return [
                    ['pattern' => 'data/(:alpha)', 'method' => 'POST', 'action' => fn (string $provider): array => Analytics::data($model, $provider)],
                    ['pattern' => 'connect', 'method' => 'POST', 'action' => fn (): array => ['url' => SearchConsole::connect()]],
                    ['pattern' => 'properties', 'method' => 'POST', 'action' => fn (): array => SearchConsole::availableProperties()],
                    ['pattern' => 'property', 'method' => 'POST', 'action' => function (): array {
                        SearchConsole::selectProperty((string)kirby()->request()->get('property', ''));
                        return ['ok' => true];
                    }],
                    ['pattern' => 'disconnect', 'method' => 'POST', 'action' => function (): array {
                        SearchConsole::disconnect();
                        return ['ok' => true];
                    }],
                ];
            },
        ],
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
        if (Seo::option('searchConsole.enabled', false) === true) {
            $routes[] = ['pattern' => '__seo/gsc/callback', 'method' => 'GET', 'action' => fn () => SearchConsole::callback()];
        }
        if (Seo::option('sitemap', true)) {
            $routes[] = ['pattern' => 'sitemap.xml', 'action' => fn () => Endpoints::sitemap()];
            $routes[] = ['pattern' => 'sitemap.xsl', 'action' => fn () => Endpoints::sitemapStylesheet()];
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
    ],
]);

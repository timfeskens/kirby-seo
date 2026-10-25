<?php

declare(strict_types=1);

use Kirby\Cms\Page;
use Kirby\Http\Response;
use TimFeskens\Seo\Endpoints;
use TimFeskens\Seo\Integrations;
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
    ],
    'pageMethods' => [
        'seo' => function (): array { return Seo::metadata($this); },
    ],
    'fields' => [
        'timfeskensSeoPreview' => [
            'save' => false,
            'computed' => [
                'preview' => function (): array {
                    $model = $this->model();
                    return Seo::metadata($model instanceof Page ? $model : $model->homePage());
                },
                'ai' => fn (): bool => Seo::option('ai.enabled', false) === true,
            ],
            'api' => function (): array {
                return [
                    [
                        'pattern' => 'preview',
                        'method' => 'POST',
                        'action' => function (): array {
                            $model = $this->model();
                            $data = kirby()->request()->get('content', []);
                            if (!is_array($data)) {
                                $data = [];
                            }
                            $allowed = ['title', 'metaTitle', 'metaTemplate', 'metaDescription', 'ogTemplate', 'ogDescription', 'ogSiteName', 'useTitleTemplate', 'useOgTemplate', 'twitterCardType', 'twitterAuthor', 'canonicalUrl', 'ogImage', 'robotsIndex', 'robotsFollow'];
                            $keys = array_map('strtolower', $allowed);
                            $data = array_intersect_key(array_change_key_case($data), array_flip($keys));
                            foreach ($data as &$value) {
                                if (is_array($value)) {
                                    $value = \Kirby\Data\Data::encode($value, 'yaml');
                                }
                            }
                            unset($value);
                            $model = $model->clone(['content' => array_replace($model->content()->toArray(), $data)]);
                            return Seo::metadata($model instanceof Page ? $model : $model->homePage()->clone(['site' => $model]));
                        },
                    ],
                    [
                        'pattern' => 'suggest',
                        'method' => 'POST',
                        'action' => function (): array {
                            $model = $this->model();
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

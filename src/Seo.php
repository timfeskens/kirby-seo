<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Content\Field;

final class Seo
{
    public static function option(string $key, mixed $default = null): mixed
    {
        return App::instance()->option('timfeskens.seo.' . $key, $default);
    }

    public static function field(Page $page, string $key, ?string $language = null): Field
    {
        $value = $page->content($language)->get($key);
        if ($value->isNotEmpty() && !in_array($value->value(), ['default', '[]'], true)) {
            return $value;
        }
        $parent = $page->parent();
        $inherit = array_map('strtolower', $parent?->content($language)->get('metaInherit')->split() ?? []);
        if ($parent && (in_array(strtolower($key), $inherit, true) || (str_starts_with(strtolower($key), 'robots') && in_array('robots', $inherit, true)))) {
            return self::field($parent, $key, $language);
        }
        $value = $page->site()->content($language)->get($key);
        return in_array($value->value(), ['default', '[]'], true) ? new Field($page->site(), $key, '') : $value;
    }

    public static function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    public static function canonical(Page $page, ?string $language = null): string
    {
        $url = $page->url($language);
        $base = self::option('canonicalBase');
        if (is_array($base)) {
            $code = $language ?? $page->kirby()->language()?->code();
            $base = $base[$code] ?? null;
        }
        // A configured base includes a possible installation subdirectory.
        if (is_string($base) && $base !== '') {
            $siteUrl = $page->site()->url();
            $sitePath = rtrim((string)parse_url($siteUrl, PHP_URL_PATH), '/');
            $path = (string)parse_url($url, PHP_URL_PATH);
            $relative = $sitePath !== '' && str_starts_with($path, $sitePath . '/') ? substr($path, strlen($sitePath)) : $path;
            if ($path === $sitePath) {
                $relative = '';
            }
            return rtrim($base, '/') . ($relative === '/' ? '' : $relative);
        }
        return $url;
    }

    public static function allowed(Page $page, string $rule, ?string $language = null): bool
    {
        if ($page->isDraft() || $page->isErrorPage()) {
            return false;
        }
        $value = self::field($page, 'robots' . ucfirst($rule), $language);
        if ($value->isNotEmpty() && $value->value() !== 'default') {
            return $value->toBool();
        }
        return $rule === 'index' ? ($page->isListed() || $page->isHomePage()) : true;
    }

    public static function indexable(Page $page, ?string $language = null): bool
    {
        $include = self::option('include');
        return self::allowed($page, 'index', $language) && (!is_callable($include) || $include($page, $language) === true);
    }

    public static function languages(Page $page): array
    {
        if (!$page->kirby()->multilang()) {
            return [null];
        }
        $codes = [];
        foreach ($page->kirby()->languages() as $language) {
            if ($page->translation($language->code())->exists()) {
                $codes[] = $language->code();
            }
        }
        return $codes;
    }

    private static function pattern(string $pattern, string $title, string $site, Page $page, ?string $language): string
    {
        // Deliberately not an arbitrary Kirby query/template evaluator.
        return strtr($pattern, [
            '{{ title }}' => $title,
            '{{ page.title }}' => (string)$page->content($language)->get('title')->value(),
            '{{ site.title }}' => $site,
        ]);
    }

    public static function metadata(Page $page, ?string $language = null): array
    {
        $get = fn (string $key) => self::field($page, $key, $language);
        $siteTitle = (string)$page->site()->content($language)->get('title')->value();
        $title = self::text($get('metaTitle')->or($page->content($language)->get('title'))->value());
        $render = fn (string $pattern) => self::text(self::pattern($pattern, $title, $siteTitle, $page, $language));
        $metaTitle = $page->content($language)->get('useTitleTemplate')->or('true')->toBool()
            ? $render($get('metaTemplate')->or('{{ title }} - {{ site.title }}')->value()) : $title;
        $ogTitle = $page->content($language)->get('useOgTemplate')->or('true')->toBool()
            ? $render($get('ogTemplate')->or('{{ title }}')->value()) : $title;
        $description = self::text($get('metaDescription')->or(self::option('description', ''))->value());
        $image = $get('ogImage')->toFile();
        $imageUrl = null;
        if ($image && $image->type() === 'image' && $image->isResizable()) {
            $imageUrl = $image->thumb(['width' => 1200, 'height' => 630, 'crop' => true])->url();
        }
        $locale = $language ? $page->kirby()->language($language)?->locale(LC_ALL) : $page->kirby()->language()?->locale(LC_ALL);
        $locale = preg_replace('/[.@].*$/', '', (string)($locale ?: self::option('locale', 'en_US')));
        $robots = [];
        foreach (['index', 'follow', 'archive', 'imageindex', 'snippet'] as $rule) {
            if (!($rule === 'index' ? self::indexable($page, $language) : self::allowed($page, $rule, $language))) {
                $robots[] = 'no' . $rule;
            }
        }
        $social = $page->site()->content($language)->get('socialMediaAccounts')->toObject();
        $twitter = '';
        if (preg_match('~^https?://(?:www\.)?(?:twitter|x)\.com/@?([a-zA-Z0-9_]+)/*$~', (string)$social->twitter()->value(), $match)) {
            $twitter = '@' . $match[1];
        }
        $creator = trim((string)$get('twitterAuthor')->value());
        $canonical = self::canonical($page, $language);
        $custom = (string)$get('canonicalUrl')->value();
        if (filter_var($custom, FILTER_VALIDATE_URL) && in_array(parse_url($custom, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $canonical = $custom;
        }
        return [
            'title' => $metaTitle,
            'canonical' => $canonical,
            'language' => str_replace('_', '-', $locale),
            'tags' => [
                'description' => $description,
                'robots' => $robots ? implode(', ', $robots) : 'index, follow',
                'og:title' => $ogTitle,
                'og:description' => self::text($get('ogDescription')->or($description)->value()),
                'og:site_name' => $render($get('ogSiteName')->or('{{ site.title }}')->value()),
                'og:type' => 'website',
                'og:locale' => $locale,
                'og:url' => $canonical,
                'og:image' => $imageUrl,
                'og:image:width' => $imageUrl ? '1200' : null,
                'og:image:height' => $imageUrl ? '630' : null,
                'og:image:alt' => $imageUrl ? $image->alt()->or($title)->value() : null,
                'twitter:card' => $get('twitterCardType')->or($imageUrl ? 'summary_large_image' : 'summary')->value(),
                'twitter:title' => $ogTitle,
                'twitter:description' => self::text($get('ogDescription')->or($description)->value()),
                'twitter:image' => $imageUrl,
                'twitter:site' => $twitter,
                'twitter:creator' => $creator ? '@' . ltrim($creator, '@') : $twitter,
            ],
        ];
    }

    public static function schema(Page $page, array $meta): array
    {
        $home = $page->site()->homePage();
        $siteUrl = self::canonical($home);
        $crumbs = [];
        foreach ($page->parents()->flip()->add($page) as $crumb) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $crumb->title()->value(), 'item' => self::canonical($crumb)];
        }
        $graph = [
            ['@type' => 'WebSite', '@id' => $siteUrl . '#website', 'url' => $siteUrl, 'name' => $page->site()->title()->value()],
            ['@type' => 'WebPage', '@id' => $meta['canonical'] . '#webpage', 'url' => $meta['canonical'], 'name' => $meta['title'], 'description' => $meta['tags']['description'], 'inLanguage' => $meta['language'], 'isPartOf' => ['@id' => $siteUrl . '#website'], 'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]],
        ];
        $extend = self::option('schema');
        if (is_callable($extend)) {
            $graph = $extend($page, $graph, $meta);
        }
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    public static function render(Page $page): string
    {
        $meta = self::metadata($page);
        $html = '<title>' . self::escape($meta['title']) . "</title>\n";
        $html .= '<link rel="canonical" href="' . self::escape($meta['canonical']) . "\">\n";
        foreach ($meta['tags'] as $name => $value) {
            if ($value !== null && $value !== '') {
                $html .= '<meta ' . (str_starts_with($name, 'og:') ? 'property' : 'name') . '="' . self::escape($name) . '" content="' . self::escape($value) . "\">\n";
            }
        }
        if ($page->kirby()->multilang()) {
            foreach (self::languages($page) as $language) {
                if (self::indexable($page, $language)) {
                    $html .= '<link rel="alternate" hreflang="' . self::escape($language) . '" href="' . self::escape(self::canonical($page, $language)) . "\">\n";
                }
            }
        }
        return $html . '<script type="application/ld+json">' . self::json(self::schema($page, $meta)) . "</script>\n";
    }
}

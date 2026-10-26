<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;

final class Endpoints
{
    public static function pages(): iterable
    {
        $site = App::instance()->site();
        foreach ($site->index()->add($site->homePage()) as $page) {
            yield $page;
        }
    }

    public static function sitemap(): Response
    {
        $escape = fn ($value) => htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        // Use the current installation URL, not a potentially cross-origin canonical base.
        $stylesheet = rtrim(App::instance()->url('index'), '/') . '/sitemap.xsl';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="' . $escape($stylesheet) . '"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (self::pages() as $page) {
            foreach (Seo::languages($page) as $language) {
                if (!self::sitemapEligible($page, $language)) {
                    continue;
                }
                $canonical = Seo::canonical($page, $language);
                $xml .= "  <url>\n    <loc>" . $escape($canonical) . "</loc>\n";
                // ISO 8601 is a PHP format; the site's intl handler interprets 'c' as a weekday.
                $modified = $page->modified('c', handler: 'date', languageCode: $language);
                if ($modified) {
                    $xml .= '    <lastmod>' . $escape($modified) . "</lastmod>\n";
                }
                $xml .= '    <priority>' . self::sitemapPriority($page, $language) . "</priority>\n";
                if ($page->kirby()->multilang()) {
                    foreach (Seo::languages($page) as $alternate) {
                        if (self::sitemapEligible($page, $alternate)) {
                            $xml .= '    <xhtml:link rel="alternate" hreflang="' . $escape($alternate) . '" href="' . $escape(Seo::canonical($page, $alternate)) . '" />' . "\n";
                        }
                    }
                }
                $xml .= "  </url>\n";
            }
        }
        return new Response($xml . "</urlset>\n", 'application/xml');
    }

    public static function sitemapStylesheet(): Response
    {
        return new Response(file_get_contents(dirname(__DIR__) . '/assets/sitemap.xsl'), 'text/xsl');
    }

    public static function sitemapPriority(Page $page, ?string $language = null): string
    {
        if ($page->isHomePage()) {
            return '1.0';
        }
        $path = trim((string)parse_url(Seo::canonical($page, $language), PHP_URL_PATH), '/');
        $home = trim((string)parse_url(Seo::canonical($page->site()->homePage(), $language), PHP_URL_PATH), '/');
        // Only content URL segments count, not the installation or language prefix.
        if ($home !== '' && str_starts_with($path, $home . '/')) {
            $path = substr($path, strlen($home) + 1);
        }
        $depth = count(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        return number_format(max(1, 10 - 2 * max(1, $depth)) / 10, 1, '.', '');
    }

    private static function sitemapEligible(Page $page, ?string $language): bool
    {
        if (!Seo::indexable($page, $language)) {
            return false;
        }
        return Seo::canonical($page, $language) === Seo::canonicalDefault($page, $language);
    }

    public static function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /panel\nDisallow: /api\nDisallow: /kirby\nDisallow: /site\nDisallow: /content\nDisallow: /vendor\n";
        if (Seo::option('sitemap', true)) {
            $home = Seo::canonical(App::instance()->site()->homePage());
            $body .= 'Sitemap: ' . rtrim($home, '/') . "/sitemap.xml\n";
        }
        return new Response($body, 'text/plain');
    }

    public static function llmEligible(Page $page): bool
    {
        $filter = Seo::option('llms.include');
        // Explicit inclusion callback required; no accidental export of arbitrary fields.
        return Seo::option('llms.enabled', false) && Seo::indexable($page) && is_callable($filter) && is_callable(Seo::option('llms.markdown')) && $filter($page) === true;
    }

    public static function llms(): Response
    {
        if (!Seo::option('llms.enabled', false)) {
            return new Response('Not found', 'text/plain', 404);
        }
        $site = App::instance()->site();
        $text = '# ' . Seo::text($site->title()) . "\n\n";
        foreach (self::pages() as $page) {
            if (self::llmEligible($page)) {
                $url = rtrim(Seo::canonical($site->homePage()), '/') . '/seo/markdown/' . rawurlencode($page->id()) . '.md';
                $text .= '- [' . str_replace(['[', ']'], '', Seo::text($page->title())) . '](' . $url . ")\n";
            }
        }
        return new Response($text, 'text/plain');
    }

    public static function markdown(string $id): Response
    {
        $page = App::instance()->page(rawurldecode($id));
        $renderer = Seo::option('llms.markdown');
        if (!$page || !self::llmEligible($page) || !is_callable($renderer)) {
            return new Response('Not found', 'text/plain', 404);
        }
        return new Response((string)$renderer($page), 'text/markdown', 200, ['X-Robots-Tag' => 'noindex']);
    }
}

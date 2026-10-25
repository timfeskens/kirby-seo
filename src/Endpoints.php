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
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        $escape = fn ($value) => htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        foreach (self::pages() as $page) {
            foreach (Seo::languages($page) as $language) {
                if (!Seo::indexable($page, $language)) {
                    continue;
                }
                $canonical = Seo::canonical($page, $language);
                $override = Seo::field($page, 'canonicalUrl', $language)->value();
                if ($override && $override !== $canonical) {
                    continue;
                }
                $xml .= '<url><loc>' . $escape($canonical) . '</loc>';
                $modified = $page->modified('c');
                if ($modified) {
                    $xml .= '<lastmod>' . $escape($modified) . '</lastmod>';
                }
                if ($page->kirby()->multilang()) {
                    foreach (Seo::languages($page) as $alternate) {
                        if (Seo::indexable($page, $alternate)) {
                            $xml .= '<xhtml:link rel="alternate" hreflang="' . $escape($alternate) . '" href="' . $escape(Seo::canonical($page, $alternate)) . '" />';
                        }
                    }
                }
                $xml .= '</url>';
            }
        }
        return new Response($xml . '</urlset>', 'application/xml');
    }

    public static function robots(): Response
    {
        $body = Seo::blocked() ? "User-agent: *\nDisallow: /\n" : "User-agent: *\nAllow: /\nDisallow: /panel\nDisallow: /api\nDisallow: /kirby\nDisallow: /site\nDisallow: /content\nDisallow: /vendor\n";
        if (!Seo::blocked() && Seo::option('sitemap', true)) {
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
        if (Seo::blocked() || !Seo::option('llms.enabled', false)) {
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

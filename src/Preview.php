<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Data;

final class Preview
{
    /** Displaying an inherited fallback must not turn it into a saved override. */
    public static function stored(ModelWithContent $model, string $name, mixed $value): mixed
    {
        if ($model instanceof Page && $model->content()->get($name)->isEmpty() && $value === self::default($model, $name)) {
            return '';
        }
        return $value;
    }

    /** Effective fallbacks, without evaluating the literal SEO pattern tokens. */
    public static function default(ModelWithContent $model, string $name): string
    {
        $name = strtolower($name);
        $patterns = ['metatemplate' => '{{ title }} - {{ site.title }}', 'ogtemplate' => '{{ title }}', 'ogsitename' => '{{ site.title }}'];
        if ($model instanceof Page) {
            $page = $model->clone(['content' => array_replace($model->content()->toArray(), [$name => ''])]);
            $inherited = Seo::field($page, $name)->value();
            if ($inherited !== null && $inherited !== '') {
                return (string)$inherited;
            }
        }
        return $patterns[$name] ?? match ($name) {
            'metadescription' => (string)Seo::option('description', ''),
            'ogdescription' => (string)$model->content()->get('metaDescription')->or(self::default($model, 'metadescription'))->value(),
            default => '',
        };
    }

    public static function metadata(ModelWithContent $model, array $data): array
    {
        $allowed = ['title', 'metaTitle', 'metaTemplate', 'metaDescription', 'ogTemplate', 'ogDescription', 'ogSiteName', 'useTitleTemplate', 'useOgTemplate', 'twitterCardType', 'twitterAuthor', 'canonicalUrl', 'ogImage', 'robotsIndex', 'robotsFollow', 'robotsArchive', 'robotsImageindex', 'robotsSnippet', 'socialMediaAccounts'];
        $data = array_intersect_key(array_change_key_case($data), array_flip(array_map('strtolower', $allowed)));
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = Data::encode($value, 'yaml');
            }
        }
        unset($value);
        $model = $model->clone(['content' => array_replace($model->content()->toArray(), $data)]);
        if ($model instanceof Page) {
            return Seo::metadata($model);
        }
        if (!$model instanceof Site) {
            throw new \InvalidArgumentException('SEO previews support pages and the site only.');
        }
        // Pass isolated unsaved site defaults explicitly, without global mutation.
        return Seo::metadata($model->homePage()->clone(['site' => $model]));
    }
}

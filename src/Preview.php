<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Data;

final class Preview
{
    public static function metadata(ModelWithContent $model, array $data): array
    {
        $allowed = ['title', 'metaTitle', 'metaTemplate', 'metaDescription', 'ogTemplate', 'ogDescription', 'ogSiteName', 'useTitleTemplate', 'useOgTemplate', 'twitterCardType', 'twitterAuthor', 'canonicalUrl', 'ogImage', 'robotsIndex', 'robotsFollow'];
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

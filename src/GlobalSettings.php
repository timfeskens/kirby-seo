<?php

declare(strict_types=1);

namespace TimFeskens\Seo;

use Kirby\Cms\App;
use Kirby\Cms\Site;
use Kirby\Data\Yaml;
use Kirby\Form\Fields;
use Kirby\Exception\PermissionException;
use Kirby\Exception\InvalidArgumentException;

/** Same site content and field serialization, independent of the site's blueprint. */
final class GlobalSettings
{
    public static function authorize(): void
    {
        if (!App::instance()->user()?->isAdmin() || !App::instance()->site()->permissions()->can('update')) {
            throw new PermissionException('Only administrators can edit global SEO settings.');
        }
    }

    public static function model(): Site
    {
        return App::instance()->site()->clone(['blueprint' => [
            'title' => 'SEO', 'tabs' => ['seo' => Yaml::read(dirname(__DIR__) . '/blueprints/tabs/site.yml')],
        ]]);
    }

    private static function fields(): Fields
    {
        $site = self::model();
        return new Fields($site->blueprint()->fields(), $site, 'current');
    }

    public static function data(): array
    {
        self::authorize();
        $fields = self::fields();
        $fields->fill(App::instance()->site()->content('current')->toArray(), false);
        $props = $fields->toProps();
        foreach ($props as $name => &$field) {
            $field['endpoints'] = ['field' => 'seo/global/fields/' . $name, 'model' => 'site'];
        }
        return [
            'fields' => $props, 'value' => $fields->toFormValues(), 'language' => App::instance()->language()?->code(),
            'languages' => App::instance()->languages()->toArray(fn ($language) => ['code' => $language->code(), 'name' => $language->name()]),
        ];
    }

    public static function save(array $input): array
    {
        self::authorize();
        $site = App::instance()->site();
        if ($site->lock()->isLocked()) throw new PermissionException('The site is being edited by another user.');
        $fields = self::fields();
        // Never accept arbitrary site fields through the SEO endpoint.
        $allowed = $fields->toProps();
        $input = array_intersect_key($input, $allowed);
        if ($input === []) throw new InvalidArgumentException('No SEO settings supplied.');
        $fields->fill(array_intersect_key(array_replace($site->content('current')->toArray(), $input), $allowed), false);
        $fields->validate();
        $values = $fields->toStoredValues();
        if ($values === []) throw new InvalidArgumentException('No SEO settings supplied.');
        $site->update($values, App::instance()->language()?->code());
        return self::data();
    }
}

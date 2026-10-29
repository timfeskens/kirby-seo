# SEO plugin compatibility

- Keep this plugin migration-compatible with
  <https://github.com/tobimori/kirby-seo>. For equivalent settings, retain its
  content-field names, stored value formats, template-variable strings, social
  profile object keys and explicit inheritance selections. Preserve existing
  content and file UUID references; do not silently rewrite migrated content.
- Check upstream conventions before adding or renaming shared fields. Document
  config/PHP API differences and unsupported features explicitly; content
  compatibility does not imply drop-in API compatibility.
- Implement independently. Do not copy proprietary upstream source or assets.
- Add migration/serialization regression tests when changing shared fields.
  Run `composer validate --strict`, `composer lint`, `composer test`,
  `node --check index.js`, and `npm test` before releasing.
- External integrations must remain optional and inactive without configuration.
  Search Console activates from credentials and a private tokenPath, without a toggle. Keep
  credentials server-side and out of Git, content, Panel props and logs.

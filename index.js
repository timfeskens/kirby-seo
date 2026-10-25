/* Native Kirby/Vue components: no bundler or runtime dependency required. */
panel.plugin("timfeskens/seo", {
  fields: {
    "timfeskens-seo-text": { extends: "k-text-field" },
    "timfeskens-seo-textarea": { extends: "k-textarea-field" },
    "timfeskens-seo-select": {
      extends: "k-select-field",
      render(h) { return h("k-select-field", { props: { ...this.$props, type: "select" }, attrs: this.$attrs, on: this.$listeners }); },
    },
    "timfeskens-seo-toggle": { extends: "k-toggle-field" },
    "timfeskens-seo-preview": {
      props: { label: String, preview: Object, endpoints: Object, ai: Boolean },
      data() {
        return { current: this.preview, timer: null, request: 0, suggestion: null, busy: false, error: "" };
      },
      computed: {
        // Kirby's model view owns the reactive, unsaved form values.
        view() {
          let parent = this.$parent;
          while (parent && !(parent.versions && parent.content)) parent = parent.$parent;
          return parent;
        },
        values() { return this.view?.content || {}; },
        nl() { return this.$panel.translation?.code?.startsWith("nl") || document.documentElement.lang.startsWith("nl"); },
      },
      watch: {
        values: {
          deep: true,
          handler() {
            clearTimeout(this.timer);
            this.timer = setTimeout(this.refresh, 300);
          },
        },
      },
      mounted() { this.refresh(); },
      beforeDestroy() { clearTimeout(this.timer); this.request++; },
      methods: {
        async refresh() {
          const request = ++this.request;
          try {
            const result = await this.$api.post(`${this.endpoints.field}/preview`, { content: this.values });
            if (request === this.request) { this.current = result; this.error = ""; }
          } catch (_) {
            if (request === this.request) this.error = this.nl ? "Het voorbeeld kon niet worden bijgewerkt." : "The preview could not be updated.";
          }
        },
        async suggest() {
          if (!window.confirm(this.nl ? "De ingestelde AI-provider ontvangt de geselecteerde, opgeslagen paginatekst. Doorgaan?" : "The configured AI provider will receive selected, saved page content. Continue?")) return;
          this.busy = true;
          this.suggestion = null;
          try {
            this.suggestion = await this.$api.post(`${this.endpoints.field}/suggest`);
            this.error = "";
          } catch (_) {
            this.error = this.nl ? "Er kon geen suggestie worden gemaakt. Controleer de AI-instellingen." : "Could not generate a suggestion. Check the AI configuration.";
          } finally { this.busy = false; }
        },
      },
      render(h) {
        const meta = this.current || this.preview || {};
        const tags = meta.tags || {};
        const card = (title, children, platform = "") => h("section", { class: ["tf-seo-card", platform && `tf-seo-${platform}`], attrs: { "aria-label": title } }, [h("h3", title), ...children]);
        const image = (src, alt) => src ? h("img", { attrs: { src, alt: alt || "", loading: "lazy" } }) : null;
        let domain = meta.canonical || "";
        try { domain = new URL(domain).hostname; } catch (_) {}
        return h("k-field", { props: { label: this.label } }, [
          h("div", { class: "tf-seo-previews" }, [
            card("Google", [
              h("p", { class: "tf-seo-url" }, meta.canonical),
              h("p", { class: "tf-seo-title" }, meta.title),
              h("p", tags.description || (this.nl ? "Vul een omschrijving in." : "Add a description.")),
              h("small", `${[...(meta.title || "")].length} / ~60 · ${[...(tags.description || "")].length} / ~160 ${this.nl ? "tekens" : "characters"}`),
            ], "google"),
            card("Facebook", [
              image(tags["og:image"], tags["og:image:alt"]),
              h("p", { class: "tf-seo-url" }, tags["og:site_name"] || domain),
              h("p", { class: "tf-seo-social-title" }, tags["og:title"]),
              h("p", tags["og:description"]),
              h("small", domain),
            ], "facebook"),
            card("Twitter / X", [
              h("div", { class: ["tf-seo-twitter-card", `tf-seo-twitter-${tags["twitter:card"] || "summary"}`] }, [
                image(tags["twitter:image"], tags["og:image:alt"]),
                h("div", [
                  h("p", { class: "tf-seo-social-title" }, tags["twitter:title"]),
                  h("p", tags["twitter:description"]),
                  h("p", { class: "tf-seo-url" }, domain),
                ]),
              ]),
              h("small", [tags["twitter:site"], tags["twitter:creator"]].filter(Boolean).join(" · ")),
            ], "twitter"),
          ]),
          h("small", { class: "tf-seo-note" }, this.nl ? "Een indicatie, geen garantie: zoekmachines en sociale platforms kunnen andere tekst en afbeeldingen tonen." : "An approximation, not a guarantee: search engines and social platforms may show different text and images."),
          h("p", { class: "tf-seo-note" }, `Robots: ${tags.robots}`),
          this.ai ? h("k-button", { props: { icon: "sparkling", disabled: this.busy }, on: { click: this.suggest } }, this.nl ? "AI-suggestie maken" : "Generate AI suggestion") : null,
          this.suggestion ? card(this.nl ? "Suggestie — controleer en neem handmatig over" : "Suggestion — review and copy manually", [h("p", this.suggestion.title), h("p", this.suggestion.description)]) : null,
          this.error ? h("p", { attrs: { role: "status" } }, this.error) : null,
        ]);
      },
    },
  },
});

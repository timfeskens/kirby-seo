/* Dedicated sidebar editor; fields keep the original site storage formats. */
panel.plugin("timfeskens/seo-settings", {
  components: {
    "timfeskens-global-seo-view": {
      props: { fields: Object, value: Object, language: String, languages: Array },
      data() { return { content: { ...this.value }, busy: false, dirty: false, error: false, saved: false }; },
      provide() { return { seoSettingsValues: () => this.content }; },
      watch: { value(value) { this.content = { ...value }; this.dirty = false; } },
      mounted() { window.addEventListener("beforeunload", this.warnUnsaved); },
      beforeDestroy() { window.removeEventListener("beforeunload", this.warnUnsaved); },
      methods: {
        warnUnsaved(event) { if (this.dirty) { event.preventDefault(); event.returnValue = ""; } },
        async save() {
          this.busy = true; this.error = false; this.saved = false;
          try {
            const result = await this.$api.patch("seo/global", { content: this.content });
            this.content = result.value; this.dirty = false; this.saved = true;
          } catch (_) { this.error = true; }
          finally { this.busy = false; }
        },
      },
      render(h) {
        const nl = this.$panel?.translation?.code === "nl";
        const text = (en, dutch) => nl ? dutch : en;
        return h("k-panel-inside", { class: "timfeskens-global-seo-view" }, [
          h("k-header", text("Global SEO settings", "Globale SEO-instellingen")),
          h("p", { class: "seo-settings-help" }, text("Global defaults for the entire website. Individual pages can override these settings.", "Standaardinstellingen voor de hele website. Afzonderlijke pagina’s kunnen hiervan afwijken.")),
          this.languages?.length ? h("label", { class: "seo-settings-help" }, [
            text("Language", "Taal") + " ",
            h("select", { domProps: { value: this.language }, on: { change: event => {
              if (this.dirty && !window.confirm(text("Discard unsaved SEO changes?", "Niet-opgeslagen SEO-wijzigingen weggooien?"))) { event.target.value = this.language; return; }
              this.dirty = false;
              window.location.assign(`${window.location.pathname}?language=${encodeURIComponent(event.target.value)}`);
            } } }, this.languages.map(language => h("option", { domProps: { value: language.code } }, language.name))),
          ]) : null,
          h("div", { class: "seo-settings-actions" }, [
            h("k-button", { props: { icon: "check", disabled: this.busy || !this.dirty }, on: { click: this.save } }, text("Save SEO settings", "SEO-instellingen opslaan")),
            this.dirty ? h("span", text("Unsaved changes", "Niet-opgeslagen wijzigingen")) : null,
          ]),
          this.error ? h("p", { attrs: { role: "alert" } }, text("Saving failed. Check the fields, permissions and site lock, then retry.", "Opslaan mislukt. Controleer de velden, rechten en sitevergrendeling en probeer opnieuw.")) : null,
          this.saved ? h("p", { attrs: { role: "status" } }, text("SEO settings saved.", "SEO-instellingen opgeslagen.")) : null,
          h("k-form", { props: { fields: this.fields, value: this.content, disabled: this.busy }, on: {
            input: value => { this.content = value; this.dirty = true; this.saved = false; },
            submit: this.save,
          } }),
        ]);
      },
    },
  },
});

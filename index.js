/* Native Kirby/Vue components: no bundler or runtime dependency required. */
panel.plugin("timfeskens/seo", {
  fields: {
    "timfeskens-seo-template": {
      props: { value: String, label: String, name: String, id: String, help: String, placeholder: String, disabled: Boolean, required: Boolean },
      data() { return { code: false, selection: null }; },
      computed: {
        nl() { return this.$panel.translation?.code?.startsWith("nl"); },
        variables() { return [
          { token: "title", label: this.nl ? "Titel" : "Title" },
          { token: "page.title", label: this.nl ? "Paginatitel" : "Page title" },
          { token: "site.title", label: this.nl ? "Websitetitel" : "Site title" },
        ]; },
      },
      watch: { value() { if (!this.code && this.read() !== (this.value || "")) this.paint(); } },
      mounted() { this.paint(); },
      methods: {
        read() {
          const read = node => node.nodeType === Node.TEXT_NODE ? node.textContent : node.dataset?.token ? `{{ ${node.dataset.token} }}` : Array.from(node.childNodes).map(read).join("");
          return this.$refs.editor ? read(this.$refs.editor).replace(/\u00a0/g, " ") : this.value || "";
        },
        paint() {
          const editor = this.$refs.editor;
          if (!editor) return;
          editor.replaceChildren();
          const pattern = this.value || "";
          const regex = /\{\{\s*(title|page\.title|site\.title)\s*\}\}/g;
          let offset = 0;
          for (const match of pattern.matchAll(regex)) {
            editor.append(document.createTextNode(pattern.slice(offset, match.index)));
            editor.append(this.chip(match[1]));
            offset = match.index + match[0].length;
          }
          editor.append(document.createTextNode(pattern.slice(offset)));
        },
        chip(token) {
          const node = document.createElement("span");
          node.className = "tf-seo-token";
          node.dataset.token = token;
          node.contentEditable = "false";
          node.textContent = this.variables.find(item => item.token === token)?.label || token;
          node.title = `{{ ${token} }}`;
          return node;
        },
        remember() {
          const selection = window.getSelection();
          if (selection.rangeCount && this.$refs.editor?.contains(selection.anchorNode) && this.$refs.editor?.contains(selection.focusNode)) this.selection = selection.getRangeAt(0).cloneRange();
        },
        insert(token) {
          if (this.disabled) return;
          const editor = this.$refs.editor;
          editor.focus();
          const range = this.selection && editor.contains(this.selection.commonAncestorContainer) ? this.selection : document.createRange();
          if (!this.selection || !editor.contains(range.commonAncestorContainer)) { range.selectNodeContents(editor); range.collapse(false); }
          range.deleteContents();
          const node = this.chip(token);
          range.insertNode(node);
          range.setStartAfter(node);
          range.collapse(true);
          const selection = window.getSelection();
          selection.removeAllRanges(); selection.addRange(range);
          this.remember();
          this.$emit("input", this.read());
        },
        paste(event) {
          event.preventDefault();
          const text = event.clipboardData.getData("text/plain").replace(/\r?\n/g, " ");
          const selection = window.getSelection();
          if (!selection.rangeCount) return;
          const range = selection.getRangeAt(0);
          range.deleteContents();
          const node = document.createTextNode(text); range.insertNode(node);
          range.setStartAfter(node); range.collapse(true); selection.removeAllRanges(); selection.addRange(range);
          this.$emit("input", this.read());
        },
        toggle() { this.code = !this.code; this.selection = null; this.$nextTick(() => this.code ? this.$refs.code?.focus() : this.paint()); },
      },
      render(h) {
        return h("k-field", { props: { label: this.label, name: this.name, help: this.help, required: this.required, input: this.id } }, [
          h("div", { class: "tf-seo-template" }, [
            h("div", { class: "tf-seo-template-toolbar" }, [
              ...(!this.code ? this.variables.map(item => h("button", { attrs: { type: "button", disabled: this.disabled, title: `{{ ${item.token} }}`, "aria-label": `${this.nl ? "Invoegen" : "Insert"}: ${item.label}` }, on: { mousedown: event => event.preventDefault(), click: () => this.insert(item.token) } }, item.label)) : []),
              h("button", { attrs: { type: "button", "aria-pressed": this.code }, on: { click: this.toggle } }, this.code ? (this.nl ? "Visueel" : "Visual") : "Code"),
            ]),
            this.code ? h("input", { ref: "code", class: "tf-seo-template-code", attrs: { id: this.id, name: this.name, disabled: this.disabled, placeholder: this.placeholder, type: "text" }, domProps: { value: this.value }, on: { input: event => this.$emit("input", event.target.value) } }) : h("div", {
              ref: "editor", class: "tf-seo-template-editor", attrs: { id: this.id, role: "textbox", "aria-label": this.label, "aria-multiline": "false", contenteditable: this.disabled ? "false" : "true" },
              on: { input: () => { this.remember(); this.$emit("input", this.read()); }, keyup: this.remember, mouseup: this.remember, blur: this.remember, paste: this.paste, keydown: event => { if (event.key === "Enter") event.preventDefault(); } },
            }),
            h("small", { class: "tf-seo-template-count" }, String((this.value || "").length)),
          ]),
        ]);
      },
    },
    "timfeskens-seo-analytics": {
      props: { label: String, providers: { type: Array, default: () => [] }, endpoints: Object },
      data() { return { results: {}, busy: {}, metric: "clicks", all: false, property: "" }; },
      computed: { nl() { return this.$panel.translation?.code?.startsWith("nl"); } },
      mounted() { this.providers.forEach(provider => this.load(provider)); },
      methods: {
        async load(provider) {
          if (this.busy[provider]) return;
          this.$set(this.busy, provider, true);
          try { this.$set(this.results, provider, await this.$api.post(`${this.endpoints.field}/data/${provider}`)); }
          catch (_) { this.$set(this.results, provider, { status: "error" }); }
          finally { this.$set(this.busy, provider, false); }
        },
        async connect() {
          try { const response = await this.$api.post(`${this.endpoints.field}/connect`); window.location.assign(response.url); }
          catch (_) { this.$set(this.results, "searchConsole", { status: "error" }); }
        },
        async select() {
          try { await this.$api.post(`${this.endpoints.field}/property`, { property: this.property }); await this.load("searchConsole"); }
          catch (_) { this.$set(this.results, "searchConsole", { status: "error" }); }
        },
        async properties() {
          try { this.$set(this.results, "searchConsole", await this.$api.post(`${this.endpoints.field}/properties`)); }
          catch (_) { this.$set(this.results, "searchConsole", { status: "error" }); }
        },
        async disconnect() {
          if (!window.confirm(this.nl ? "Google Search Console loskoppelen?" : "Disconnect Google Search Console?")) return;
          try { await this.$api.post(`${this.endpoints.field}/disconnect`); await this.load("searchConsole"); }
          catch (_) { this.$set(this.results, "searchConsole", { status: "error" }); }
        },
        format(value, metric) { return new Intl.NumberFormat(this.nl ? "nl-NL" : "en", metric === "ctr" ? { style: "percent", maximumFractionDigits: 1 } : { maximumFractionDigits: metric === "position" ? 1 : 0 }).format(value); },
      },
      render(h) {
        if (!this.providers.length) return null;
        const text = (en, nl) => this.nl ? nl : en;
        const button = (label, click, disabled = false) => h("button", { class: "tf-seo-analytics-button", attrs: { type: "button", disabled }, on: { click } }, label);
        const metrics = { clicks: text("Clicks", "Klikken"), impressions: text("Impressions", "Vertoningen"), ctr: "CTR", position: text("Position", "Positie") };
        const messages = {
          credentials: text("Configure server-side credentials to use this integration.", "Stel de inloggegevens in de serverconfiguratie in om deze koppeling te gebruiken."),
          disconnected: text("Connect your Google account with read-only access.", "Verbind je Google-account met alleen-lezen toegang."),
          property: text("Select a Search Console property. Only an administrator can change the connection.", "Kies een Search Console-property. Alleen een beheerder kan de koppeling wijzigen."),
          error: text("Could not load data. Check credentials, access, private token storage and API quota.", "Gegevens konden niet worden geladen. Controleer de inloggegevens, toegang, privéopslag voor tokens en het API-quotum."),
          excluded: text("Analytics are not requested for pages excluded from indexing.", "Voor pagina’s die niet geïndexeerd mogen worden, worden geen gegevens opgevraagd."),
        };
        const errors = {
          apiDisabled: text("Enable the Google Search Console API in the Google Cloud project used by your OAuth credentials, then retry. Reconnecting will not enable the API.", "Schakel de Google Search Console API in voor het Google Cloud-project van je OAuth-inloggegevens en probeer opnieuw. Opnieuw verbinden schakelt de API niet in."),
          reconnect: text("Google access has expired or was revoked. Reconnect your Google account.", "De Google-toegang is verlopen of ingetrokken. Verbind je Google-account opnieuw."),
          accessDenied: text("This Google account cannot access the selected property. Check Search Console permissions or choose another property.", "Dit Google-account heeft geen toegang tot de gekozen property. Controleer de rechten in Search Console of kies een andere property."),
          quota: text("The API quota has been reached. Try again later or check the quota in Google Cloud.", "Het API-quotum is bereikt. Probeer het later opnieuw of controleer het quotum in Google Cloud."),
        };
        return h("div", { class: "tf-seo-analytics" }, [h("h2", this.label || text("Search performance", "Zoekprestaties")), ...this.providers.map(provider => {
          const result = this.results[provider];
          const gsc = provider === "searchConsole";
          const children = [h("h3", gsc ? "Google Search Console" : "Ahrefs")];
          if (!result || this.busy[provider]) children.push(h("div", { class: "tf-seo-skeleton", attrs: { role: "status", "aria-label": text("Loading reports", "Rapporten laden") } }, [h("span"), h("span"), h("span")]));
          else if (result.status !== "connected") {
            children.push(h("p", { attrs: { role: "status" } }, !gsc && result.status === "credentials" ? text("Free Domain Rating needs a free Ahrefs API key. Add it to private config; no paid subscription is required.", "Gratis Domain Rating vereist een gratis Ahrefs API-sleutel. Voeg deze toe aan de privéconfiguratie; een betaald abonnement is niet nodig.") : (gsc && result.status === "error" && errors[result.reason]) || messages[result.status] || messages.error));
            if (gsc && result.reason === "apiDisabled" && result.admin) children.push(h("a", { attrs: { href: "https://console.cloud.google.com/apis/library/searchconsole.googleapis.com", target: "_blank", rel: "noopener noreferrer" } }, text("Enable Search Console API", "Search Console API inschakelen")));
            if (!gsc && result.status === "credentials") children.push(h("a", { attrs: { href: "https://app.ahrefs.com/account/api-keys", target: "_blank", rel: "noopener noreferrer" } }, text("Create a free API key", "Gratis API-sleutel aanmaken")));
            if (gsc && result.admin && result.status === "disconnected") children.push(button(text("Connect Google", "Google verbinden"), this.connect));
            if (gsc && result.admin && result.status === "property") children.push(
              h("select", { attrs: { "aria-label": "Search Console property" }, domProps: { value: this.property }, on: { change: event => { this.property = event.target.value; } } }, [h("option", { attrs: { value: "" } }, text("Select property", "Kies een property")), ...(result.properties || []).map(property => h("option", { domProps: { value: property } }, property))]),
              button(text("Use property", "Property gebruiken"), this.select, !this.property),
            );
            if (result.status === "error") children.push(button(text("Retry", "Opnieuw proberen"), () => this.load(provider)));
            if (gsc && result.admin && result.status === "error") children.push(button(text("Reconnect", "Opnieuw verbinden"), this.connect), button(text("Disconnect", "Loskoppelen"), this.disconnect));
            if (gsc && result.admin && result.reason === "accessDenied") children.push(button(text("Change property", "Property wijzigen"), this.properties));
          } else if (gsc) {
            const rows = [...(result.rows || [])].sort((a, b) => this.metric === "position" ? a.position - b.position : b[this.metric] - a[this.metric]);
            const visible = this.all ? rows : rows.slice(0, 10);
            children.push(h("p", { class: "tf-seo-note" }, `${result.startDate} – ${result.endDate} · ${result.property}`));
            const totals = rows.reduce((sum, row) => ({ clicks: sum.clicks + row.clicks, impressions: sum.impressions + row.impressions, weighted: sum.weighted + row.position * row.impressions }), { clicks: 0, impressions: 0, weighted: 0 });
            const summary = { clicks: totals.clicks, impressions: totals.impressions, ctr: totals.impressions ? totals.clicks / totals.impressions : 0, position: totals.impressions ? totals.weighted / totals.impressions : 0 };
            children.push(h("dl", { class: "tf-seo-metrics" }, Object.entries(summary).flatMap(([key, value]) => [h("dt", metrics[key]), h("dd", this.format(value, key))])));
            children.push(h("small", { class: "tf-seo-note" }, text("Summary of returned queries; Google may omit anonymized queries.", "Samenvatting van de opgehaalde zoekopdrachten; Google kan geanonimiseerde zoekopdrachten weglaten.")));
            children.push(h("select", { attrs: { "aria-label": text("Sort queries", "Zoekopdrachten sorteren") }, domProps: { value: this.metric }, on: { change: event => { this.metric = event.target.value; } } }, Object.entries(metrics).map(([value, label]) => h("option", { domProps: { value } }, label))));
            if (!rows.length) children.push(h("p", text("No search queries in this period.", "Geen zoekopdrachten in deze periode.")));
            else children.push(h("div", { class: "tf-seo-table-scroll", attrs: { tabindex: "0" } }, [h("table", [
              h("thead", [h("tr", [h("th", text("Query", "Zoekopdracht")), ...Object.values(metrics).map(label => h("th", label))])]),
              h("tbody", visible.map(row => h("tr", [h("td", row.query), ...Object.keys(metrics).map(metric => h("td", this.format(row[metric], metric)))]))),
            ])]));
            if (rows.length > 10) children.push(button(this.all ? text("Show top 10", "Toon top 10") : text("Show all", "Alles tonen"), () => { this.all = !this.all; }));
            if (result.admin) children.push(button(text("Change property", "Property wijzigen"), this.properties), button(text("Reconnect", "Opnieuw verbinden"), this.connect), button(text("Disconnect", "Loskoppelen"), this.disconnect));
          } else {
            const labels = { domain_rating: "Domain Rating", org_keywords: text("Organic keywords", "Organische zoekwoorden"), org_keywords_1_3: text("Top 3 keywords", "Zoekwoorden in top 3"), org_traffic: text("Estimated monthly organic traffic", "Geschat maandelijks organisch verkeer"), paid_keywords: text("Paid keywords", "Betaalde zoekwoorden") };
            children.push(h("dl", Object.entries(result.metrics || {}).flatMap(([key, value]) => [h("dt", labels[key] || key), h("dd", value === null ? "—" : this.format(value, key))])));
            if (result.mode === "free") children.push(h("a", { attrs: { href: "https://ahrefs.com/", target: "_blank", rel: "noopener noreferrer" } }, "Domain Rating by Ahrefs"), h("p", { class: "tf-seo-note" }, text("Free domain-wide backlink rating (0–100), not page traffic or a Google ranking score.", "Gratis domeinbrede backlinkscore (0–100), geen paginaverkeer of Google-rangschikkingsscore.")));
            else children.push(h("p", { class: "tf-seo-note" }, `${result.date} · ${text("Ahrefs estimates, not measured visits", "Schattingen van Ahrefs, geen gemeten bezoeken")}`));
          }
          children.push(h("a", { attrs: { href: gsc ? "https://search.google.com/search-console" : "https://app.ahrefs.com/site-explorer", target: "_blank", rel: "noopener noreferrer" } }, text("Open in ", "Open in ") + (gsc ? "Google Search Console" : "Ahrefs")));
          children.push(h("small", { class: "tf-seo-note" }, text("Results cached for 24 hours.", "Resultaten worden 24 uur bewaard.")));
          return h("section", { class: "tf-seo-card", attrs: { "aria-label": gsc ? "Google Search Console" : "Ahrefs", "aria-busy": String(!result || !!this.busy[provider]) } }, children);
        })]);
      },
    },
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
        return { current: this.preview || null, timer: null, request: 0, suggestion: null, busy: false, error: "", refreshing: false, pending: false, disposed: false };
      },
      computed: {
        // Read Kirby's reactive form store, not a component ancestor. The store
        // is merged synchronously on input, before lazy draft persistence.
        values() {
          const content = this.$panel.content.version("changes") || {};
          const keys = ["title", "metatitle", "metatemplate", "metadescription", "metainherit", "ogtemplate", "ogdescription", "ogsitename", "usetitletemplate", "useogtemplate", "twittercardtype", "twitterauthor", "ogimage", "cropogimage", "robotsindex", "robotsfollow", "robotsarchive", "robotsimageindex", "robotssnippet", "socialmediaaccounts"];
          return Object.fromEntries(keys.filter(key => key in content).map(key => [key, content[key]]));
        },
        signature() { return JSON.stringify(this.values); },
        nl() { return this.$panel.translation?.code?.startsWith("nl") || document.documentElement.lang.startsWith("nl"); },
      },
      watch: {
        signature: {
          handler() {
            // Invalidate older responses immediately and throttle (not debounce)
            // so the preview also updates during uninterrupted typing.
            this.request++;
            if (!this.timer) this.timer = setTimeout(() => { this.timer = null; this.refresh(); }, 150);
          },
        },
      },
      mounted() { this.refresh(); },
      beforeDestroy() { clearTimeout(this.timer); this.request++; this.disposed = true; },
      methods: {
        async refresh() {
          if (this.disposed) return;
          // At most one request in flight; coalesce edits into one trailing update.
          if (this.refreshing) { this.pending = true; return; }
          const request = ++this.request;
          this.refreshing = true;
          try {
            const result = await this.$api.post(`${this.endpoints.field}/preview`, { content: this.values });
            if (request === this.request) { this.current = result; this.error = ""; }
          } catch (_) {
            if (request === this.request) this.error = this.nl ? "Het voorbeeld kon niet worden bijgewerkt." : "The preview could not be updated.";
          } finally {
            this.refreshing = false;
            if (this.pending) { this.pending = false; this.refresh(); }
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
        if (!this.current && !this.preview) return h("k-field", { props: { label: this.label } }, [
          h("div", { class: "tf-seo-previews", attrs: { "aria-busy": String(this.refreshing) } }, ["Google", "Facebook", "Twitter / X"].map(title => card(title, [
            this.error ? h("p", { attrs: { role: "status" } }, this.error) : h("div", { class: ["tf-seo-skeleton", title !== "Google" && "tf-seo-skeleton-social"], attrs: { role: "status", "aria-label": this.nl ? "Voorbeeld laden" : "Loading preview" } }, [h("span"), h("span"), h("span")]),
          ]))),
          this.error ? h("k-button", { props: { icon: "refresh", disabled: this.refreshing }, on: { click: this.refresh } }, this.nl ? "Opnieuw proberen" : "Retry") : null,
        ]);
        let domain = meta.canonical || "";
        try { domain = new URL(domain).hostname; } catch (_) {}
        return h("k-field", { props: { label: this.label } }, [
          meta.scope === "site-defaults" ? h("p", { class: "tf-seo-note" }, [
            this.nl ? "Voorbeeld van de websitestandaarden met de homepagina als voorbeeld. Eigen SEO-instellingen van pagina’s hebben voorrang op de website. " : "Site defaults using the homepage as an example. Page-specific SEO settings take precedence on the website. ",
            h("a", { attrs: { href: meta.homePanelUrl } }, this.nl ? "SEO van de homepagina bewerken" : "Edit homepage SEO"),
          ]) : null,
          h("div", { class: "tf-seo-previews", attrs: { "aria-busy": String(this.refreshing) } }, [
            card("Google", [
              h("p", { class: "tf-seo-url" }, meta.canonical),
              h("p", { class: "tf-seo-title" }, meta.title),
              h("p", tags.description || (this.nl ? "Vul een omschrijving in." : "Add a description.")),
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

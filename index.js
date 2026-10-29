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
      inject: { seoSettingsValues: { default: null } },
      props: { label: String, preview: Object, endpoints: Object, ai: Boolean },
      data() {
        return { current: this.preview || null, timer: null, request: 0, suggestion: null, busy: false, error: "", refreshing: false, pending: false, disposed: false };
      },
      computed: {
        // Read Kirby's reactive form store, not a component ancestor. The store
        // is merged synchronously on input, before lazy draft persistence.
        values() {
          const content = this.seoSettingsValues ? this.seoSettingsValues() : (this.$panel.content.version("changes") || {});
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

/* Search Console dashboard, no build step. */
panel.plugin("timfeskens/seo-dashboard", {
  components: {
    "timfeskens-search-console-view": {
      props: { report: Object },
      data() { return { busy: false, failed: false, properties: null, property: "" }; },
      watch: { report() { this.properties = null; this.property = ""; this.failed = false; } },
      methods: {
        async manage(action) {
          this.busy = true;
          this.failed = false;
          try {
            const result = await this.$api.post(`seo/search-console/${action}`, { property: this.property });
            if (result.status === "error") throw new Error();
            if (action === "connect") window.location.assign(result.url);
            else if (action === "properties") this.properties = result.properties || [];
            else window.location.reload();
          } catch (_) { this.failed = true; }
          finally { this.busy = false; }
        },
        number(value, key) {
          if (value === null || value === undefined || (key === "position" && !value)) return "—";
          return key === "ctr" ? Number(value).toLocaleString("nl-NL", { style: "percent", maximumFractionDigits: 2 })
            : Number(value).toLocaleString("nl-NL", { maximumFractionDigits: key === "position" ? 1 : 0 });
        },
        button(h, label, action, icon = "refresh") {
          return h("k-button", { props: { icon, disabled: this.busy }, on: { click: () => this.manage(action) } }, label);
        },
        table(h, title, label, rows) {
          const columns = [["label", label], ["clicks", "Klikken"], ["impressions", "Vertoningen"], ["ctr", "CTR"], ["position", "Positie"]];
          return h("section", { class: "traffic-section" }, [
            h("h2", title),
            rows.length ? h("div", { class: "traffic-table-scroll", attrs: { tabindex: "0", role: "region", "aria-label": title } }, [
              h("table", [
                h("thead", [h("tr", columns.map(([, name]) => h("th", { attrs: { scope: "col" } }, name)))]),
                h("tbody", rows.map(row => h("tr", columns.map(([key]) => h("td", key === "label" ? row[key] : this.number(row[key], key)))))),
              ]),
            ]) : h("p", { class: "traffic-muted" }, "Nog geen gegevens in deze periode."),
          ]);
        },
        chart(h, key, title) {
          const rows = this.report.daily;
          const values = rows.map(row => row[key]).filter(value => value !== null);
          const max = Math.max(...values, key === "ctr" ? 0.01 : 1);
          const points = rows.map((row, i) => row[key] === null ? null : `${40 + i * 600 / Math.max(rows.length - 1, 1)},${160 - row[key] * 130 / max}`);
          // Split missing positions into separate segments rather than suggesting a rank of zero.
          const segments = points.join(" ").split(/\s{2,}/).filter(segment => segment.trim());
          const description = `${title} per dag van ${this.report.startDate} tot ${this.report.endDate}. Exacte waarden staan in de tabel Per dag.`;
          return h("section", { class: `traffic-chart traffic-chart-${key}` }, [
            h("h2", title),
            h("p", { class: "traffic-muted" }, `Schaal: 0 – ${this.number(max, key)}`),
            h("svg", { attrs: { viewBox: "0 0 660 170", role: "img", "aria-label": description } }, [
              h("title", description),
              h("line", { attrs: { x1: 40, x2: 640, y1: 160, y2: 160, class: "traffic-chart-axis" } }),
              ...segments.map(segment => h("polyline", { attrs: { points: segment.trim(), fill: "none", "stroke-width": 2, "vector-effect": "non-scaling-stroke" } })),
              ...rows.map((row, i) => row[key] === null ? null : h("circle", { attrs: { cx: 40 + i * 600 / Math.max(rows.length - 1, 1), cy: 160 - row[key] * 130 / max, r: 2 } }, [h("title", `${row.label}: ${this.number(row[key], key)}`)])),
            ]),
            h("div", { class: "traffic-chart-dates traffic-muted" }, [h("span", rows[0]?.label), h("span", rows[rows.length - 1]?.label)]),
            key === "position" ? h("p", { class: "traffic-muted" }, "Een lagere positie is beter. Dagen zonder vertoningen hebben geen positie.") : null,
          ]);
        },
      },
      render(h) {
        const r = this.report;
        const metrics = [["clicks", "Klikken"], ["impressions", "Vertoningen"], ["ctr", "Gemiddelde CTR"], ["position", "Gemiddelde positie"]];
        const messages = {
          credentials: "Configureer timfeskens.seo.searchConsole.credentials en een privé tokenPath buiten de webroot. De koppeling wordt automatisch beschikbaar zodra deze zijn ingesteld. Activeer de Search Console API en registreer /__seo/gsc/callback als omleidings-URI.",
          disconnected: "Koppel je Google-account om zoekprestaties te bekijken. We vragen alleen leestoegang tot Search Console.",
          property: "Selecteer de Search Console-property van deze website. Er wordt nooit automatisch een property gekozen.",
          error: ({ apiDisabled: "Schakel de Search Console API in voor het Google Cloud-project.", reconnect: "De Google-koppeling is verlopen of ingetrokken. Koppel je account opnieuw.", accessDenied: "Je Google-account heeft geen toegang tot deze property. Controleer de rechten of kies een andere property.", quota: "De Google API-limiet is bereikt. Probeer het later opnieuw." })[r.reason] || "Zoekgegevens zijn tijdelijk niet beschikbaar. Controleer de koppeling en probeer het opnieuw.",
        };
        const properties = this.properties || r.properties;
        return h("k-panel-inside", { class: "timfeskens-traffic-view" }, [
          h("k-header", "Verkeer · Google Search Console"),
          h("div", { class: "traffic-actions" }, [
            h("k-button", { props: { icon: "refresh", disabled: this.busy }, on: { click: () => window.location.reload() } }, "Vernieuwen"),
            ["connected", "property"].includes(r.status) || r.reason === "accessDenied" ? this.button(h, "Property kiezen", "properties", "settings") : null,
            ["connected", "property", "error"].includes(r.status) ? this.button(h, "Ontkoppelen", "disconnect", "cancel") : null,
          ]),
          h("nav", { class: "traffic-period", attrs: { "aria-label": "Rapportperiode" } }, [7, 30, 90].map(days =>
            h("k-link", { props: { to: `traffic/${days}` }, attrs: { "aria-current": r.days === days ? "page" : null } }, `Laatste ${days} dagen`))),
          h("p", { class: "traffic-intro" }, "Organische Google-zoekresultaten (web), geen websitebezoeken of advertentieverkeer. Google verwerkt gegevens met vertraging; de laatste drie dagen zijn uitgesloten. Datums volgen de Pacific-tijdzone van Search Console. Rapporten worden maximaal een uur gecachet."),
          messages[r.status] ? h("p", { class: "traffic-warning", attrs: { role: "status" } }, messages[r.status]) : null,
          this.failed ? h("p", { class: "traffic-warning", attrs: { role: "alert" } }, "Actie mislukt. Controleer de configuratie en probeer opnieuw.") : null,
          r.status === "disconnected" || (r.status === "error" && r.reason === "reconnect") ? this.button(h, "Koppelen met Google", "connect", "url") : null,
          r.status === "error" && r.reason === "apiDisabled" ? h("a", { attrs: { href: "https://console.cloud.google.com/apis/library/searchconsole.googleapis.com", target: "_blank", rel: "noopener noreferrer" } }, "Search Console API inschakelen") : null,
          properties ? h("div", { class: "traffic-property" }, [
            h("label", { attrs: { for: "traffic-property" } }, "Search Console-property"),
            h("select", { attrs: { id: "traffic-property", disabled: this.busy }, domProps: { value: this.property }, on: { change: event => { this.property = event.target.value; } } }, [
              h("option", { attrs: { value: "" } }, "Kies een property…"),
              ...properties.map(property => h("option", { domProps: { value: property } }, property)),
            ]),
            this.property ? this.button(h, "Property opslaan", "property", "check") : null,
            !properties.length ? h("p", { class: "traffic-muted" }, "Dit Google-account heeft geen beschikbare properties.") : null,
          ]) : null,
          r.status === "connected" ? h("div", [
            h("p", { class: "traffic-intro" }, `${r.property} · ${r.startDate} t/m ${r.endDate}`),
            h("div", { class: "traffic-stats" }, metrics.map(([key, label]) => h("div", [h("strong", this.number(r.totals[key], key)), h("span", label)]))),
            !r.totals.impressions ? h("p", { class: "traffic-empty" }, "Google heeft voor deze property en periode nog geen zoekgegevens. Probeer een langere periode.") : null,
            h("div", { class: "traffic-charts" }, metrics.map(([key, label]) => this.chart(h, key, label))),
            this.table(h, "Zoekopdrachten", "Zoekopdracht", r.queries),
            this.table(h, "Pagina’s", "Pagina", r.pages),
            this.table(h, "Landen", "Landcode", r.countries),
            this.table(h, "Apparaten", "Apparaat", r.devices),
            this.table(h, "Per dag", "Datum", r.daily),
            h("p", { class: "traffic-intro" }, "Tabellen tonen maximaal 100 toprijen. Google verbergt sommige zoekopdrachten om privacyredenen; tabelrijen tellen daarom niet altijd op tot het totaal. Totalen en daggrafieken worden afzonderlijk opgevraagd, CTR en positie worden door Google berekend."),
          ]) : null,
          h("p", { class: "traffic-intro" }, [h("a", { attrs: { href: "https://search.google.com/search-console", target: "_blank", rel: "noopener noreferrer" } }, "Open Google Search Console")]),
        ]);
      },
    },
  },
});

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

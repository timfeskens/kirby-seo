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
          const theme = action === "connect" || action === "property" ? "positive" : action === "disconnect" ? "negative" : undefined;
          return h("k-button", { props: { icon, variant: "filled", theme, disabled: this.busy }, on: { click: () => this.manage(action) } }, label);
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
        const needsConnection = ["credentials", "disconnected"].includes(r.status) || r.reason === "reconnect";
        return h("k-panel-inside", { class: "timfeskens-traffic-view" }, [
          h("k-header", "Verkeer · Google Search Console"),
          !needsConnection ? h("div", { class: "traffic-actions" }, [
            h("k-button", { props: { icon: "refresh", variant: "filled", disabled: this.busy }, on: { click: () => window.location.reload() } }, "Vernieuwen"),
            ["connected", "property"].includes(r.status) || r.reason === "accessDenied" ? this.button(h, "Property kiezen", "properties", "settings") : null,
            ["connected", "property", "error"].includes(r.status) ? this.button(h, "Ontkoppelen", "disconnect", "cancel") : null,
          ]) : null,
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
          h("p", { class: "traffic-external" }, [h("k-button", { props: { link: "https://search.google.com/search-console", target: "_blank", rel: "noopener noreferrer", icon: "url", variant: "filled" } }, "Open Google Search Console")]),
        ]);
      },
    },
  },
});

# TradeFlow

TradeFlow is a focused service-booking platform for local trade businesses. It combines a custom WordPress plugin and theme with a React/TypeScript booking experience, operational lead management, location eligibility rules, campaign attribution, and measurable performance budgets.

## MVP workflow

1. A customer lands on a location-specific service page.
2. The React widget checks their postal code, collects job details and photos, and offers appointment windows.
3. The REST API validates the request and rejects duplicates at the database layer.
4. Staff assign a technician and update status from **TradeFlow → Leads** in WordPress Admin.
5. WordPress sends customer confirmation and status emails.
6. UTM, referrer, landing-page and GA4/GTM events preserve marketing attribution.

## Try the booking demo (no WordPress required)

```bash
npm ci
npm run demo
```

Open `http://127.0.0.1:4173/demo/`. This runs the actual booking widget and theme styles against a **disposable development API**. Use Toronto postal code `M5V 2T6`, fictitious job/contact details, and the sample arrival window. Success shows `TF-DEMO`; nothing is saved and no email is sent. It is not a replacement for the WordPress backend.

The widget ignores outdated eligibility responses after address edits, offers Retry loading on startup failure, and retains the in-memory draft after failed submission. Refreshing still discards the draft. The demo is loopback-only and must not be used as a public server.

## Run locally

Requirements: Docker Desktop, Node.js 20+, and npm.

```bash
cp .env.example .env
npm install
npm run build
docker compose up -d
docker compose --profile tools run --rm wpcli core install \
  --url=http://localhost:8080 \
  --title=TradeFlow \
  --admin_user=admin \
  --admin_password=tradeflow_local \
  --admin_email=admin@example.test
docker compose --profile tools run --rm wpcli plugin activate tradeflow-core
docker compose --profile tools run --rm wpcli theme activate tradeflow
docker compose --profile tools run --rm wpcli rewrite structure '/%postname%/' --hard
```

Visit `http://localhost:8080`. Activation seeds three services and three Greater Toronto service areas. Local email is written to the WordPress debug log unless SMTP is configured.

## Quality checks

```bash
npm test
npm run typecheck
npm run build
npx playwright install chromium
npm run test:widget # desktop/mobile widget + disposable API; no Docker
npm run test:e2e
npm run lighthouse
composer install
composer test
```

The local Lighthouse runner writes private reports to `.lighthouseci/reports`. CI runs three Lighthouse CI samples and enforces 90+ performance, accessibility and SEO scores, LCP under 2.5 seconds, CLS under 0.1, and a 200 ms interaction budget.

## Deploy on Render

The repository includes a production multi-stage WordPress image and a free Render Blueprint configured for Aiven MySQL, verified TLS, automatic first-run setup, and health checks. Bookings persist in MySQL; media uploads are ephemeral on Render Free and are intended only for demonstrating the workflow.

Follow [the Render and Aiven deployment guide](docs/DEPLOY_RENDER.md). Render account access, an Aiven service, the database credentials, and the final public URL are required to provision the live environment.

## Technical documentation

- [Purpose, acceptance criteria, references, architecture and journal](docs/ENGINEERING.md)
- [Defect investigations and regression evidence](docs/INVESTIGATIONS.md)
- [Walkthrough, verification and interview guide](docs/INTERVIEW.md)
- [Measured resume impact and reproducible evidence](docs/RESUME_IMPACT.md)
- [Changelog](CHANGELOG.md)

## Production notes

- Current dependency audit reports 31 development-toolchain findings, including 3 critical; production-only npm audit reports zero. Upgrade and reverify tooling before exposing development services or accepting untrusted build/test input. See [verification notes](docs/INTERVIEW.md#current-verification). This is not a production-readiness certification.
- Replace local credentials, enable HTTPS, configure SMTP, and set an explicit GA4/GTM container before launch.
- Keep page caching disabled on `/wp-json/tradeflow/v1/*`; cache public pages at the edge.
- Uploads use WordPress media handling, MIME allow-lists and size limits. Add malware scanning for higher-volume production use.
- Appointment creation and lead creation run in one MySQL transaction; the unique dedupe key prevents concurrent duplicate submissions.
- Service and service-area custom post types live in the plugin so their content remains portable if the theme changes.

# TradeFlow

TradeFlow is a focused service-booking platform for local trade businesses. It combines a custom WordPress plugin and theme with a React/TypeScript booking experience, operational lead management, location eligibility rules, campaign attribution, and measurable performance budgets.

## MVP workflow

1. A customer lands on a location-specific service page.
2. The React widget checks their postal code, collects job details and photos, and offers appointment windows.
3. The REST API validates the request and rejects duplicates at the database layer.
4. Staff assign a technician and update status from **TradeFlow → Leads** in WordPress Admin.
5. WordPress sends customer confirmation and status emails.
6. UTM, referrer, landing-page and GA4/GTM events preserve marketing attribution.

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
npm run build
npm run test:e2e
npm run lighthouse
composer install
composer test
```

The local Lighthouse runner writes private reports to `.lighthouseci/reports`. CI runs three Lighthouse CI samples and enforces 90+ performance, accessibility and SEO scores, LCP under 2.5 seconds, CLS under 0.1, and a 200 ms interaction budget.

## Deploy on Render

The repository includes a production multi-stage WordPress image and a Render Blueprint configured for Aiven MySQL, verified TLS, automatic first-run setup, health checks, and persistent media uploads.

Follow [the Render and Aiven deployment guide](docs/DEPLOY_RENDER.md). Render account access, an Aiven service, the database credentials, and the final public URL are required to provision the live environment.

## Production notes

- Replace local credentials, enable HTTPS, configure SMTP, and set an explicit GA4/GTM container before launch.
- Keep page caching disabled on `/wp-json/tradeflow/v1/*`; cache public pages at the edge.
- Uploads use WordPress media handling, MIME allow-lists and size limits. Add malware scanning for higher-volume production use.
- Appointment creation and lead creation run in one MySQL transaction; the unique dedupe key prevents concurrent duplicate submissions.
- Service and service-area custom post types live in the plugin so their content remains portable if the theme changes.

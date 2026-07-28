# Deploy TradeFlow on Render with Aiven MySQL

This deployment runs WordPress as a free Docker web service on Render and stores relational data in Aiven for MySQL. The first start installs WordPress, activates the TradeFlow plugin and theme, seeds the MVP content, and configures permalinks.

Render Free does not support persistent disks. Leads, appointments, attribution, WordPress content, and settings persist in Aiven MySQL, but uploaded photos are removed whenever the Render instance restarts or redeploys.

## 1. Create the Aiven database

1. Create an Aiven for MySQL service using the **Free** plan. Aiven selects the cloud and region for free services.
2. Keep TLS enabled and note the host, port, database name, username, and password from the Aiven service overview.
3. Download the project's CA certificate as `aiven-ca.pem`.
4. Leave Aiven's IP filter open during the first deployment. Restrict it later only if the Render service uses stable outbound IPs.

Use the host and port together for `WORDPRESS_DB_HOST`, for example:

```text
mysql-project.aivencloud.com:24876
```

## 2. Create the Render Blueprint

Push this repository to GitHub, GitLab, or Bitbucket. In Render, choose **New → Blueprint**, connect the repository, and use the root-level `render.yaml`.

Render prompts for every value marked `sync: false`:

| Variable                   | Value                                         |
| -------------------------- | --------------------------------------------- |
| `WORDPRESS_DB_HOST`        | Aiven hostname and port, joined with `:`      |
| `WORDPRESS_DB_NAME`        | Aiven database name, commonly `defaultdb`     |
| `WORDPRESS_DB_USER`        | Aiven service username                        |
| `WORDPRESS_DB_PASSWORD`    | Aiven service password                        |
| `TRADEFLOW_SITE_URL`       | Final HTTPS URL without a trailing slash      |
| `TRADEFLOW_ADMIN_USER`     | New WordPress administrator username          |
| `TRADEFLOW_ADMIN_PASSWORD` | A unique, high-entropy administrator password |
| `TRADEFLOW_ADMIN_EMAIL`    | Administrator email address                   |

For the first deployment, the site URL can be the expected Render address, such as `https://tradeflow-booking.onrender.com`. If Render assigns a different hostname, update `TRADEFLOW_SITE_URL` and redeploy.

## 3. Add the Aiven CA

In the Render service, open **Environment → Secret Files**, add a file named `aiven-ca.pem`, and paste the downloaded Aiven CA certificate. It appears at `/etc/secrets/aiven-ca.pem` at runtime. The startup script installs it into the operating-system trust store before WordPress connects.

The WordPress database connection requires TLS and verifies the MySQL server certificate. The uploaded project CA extends the image's public trust store for Aiven services that do not use a browser-recognized certificate.

## 4. Verify the deployment

After the deploy becomes healthy:

1. Open the site and complete a test booking with a small JPG, PNG, or WebP.
2. Sign in at `/wp-admin/`, then confirm the lead appears under **TradeFlow → Leads**.
3. Assign a technician and change the status to confirm the staff workflow.
4. Confirm leads and appointments remain available after a redeploy. Uploaded photos are expected to be temporary on Render Free.
5. Open `/wp-json/tradeflow/v1/health`; it should return `status: ok`.

Set SMTP and GA4/GTM values before treating the environment as production. Aiven keeps the relational data and database backups.

## Operational trade-offs

- Render Free spins down after inactivity and does not preserve `wp-content/uploads`.
- Plugin, theme, and WordPress changes ship through the Docker image.
- Aiven Free is a single-node, 1 GB service intended for demos and small workloads.
- Upgrade Render to a paid service and attach `/var/www/html/wp-content/uploads` as a persistent disk before using photo uploads for real customer work.

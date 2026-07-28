# Deploy TradeFlow on Render with Aiven MySQL

This deployment runs WordPress as a Docker web service on Render, stores relational data in Aiven for MySQL, and keeps media uploads on a Render persistent disk. The first start installs WordPress, activates the TradeFlow plugin and theme, seeds the MVP content, and configures permalinks.

## 1. Create the Aiven database

1. Create an Aiven for MySQL service in the cloud region nearest the Render service.
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
4. Redeploy the same commit and verify the uploaded photo still loads.
5. Open `/wp-json/tradeflow/v1/health`; it should return `status: ok`.

Set SMTP and GA4/GTM values before treating the environment as production. Render's persistent disk keeps uploads, while Aiven owns database backups and recovery.

## Operational trade-offs

- Render persistent disks require a paid service and limit the web service to one instance.
- A disk-backed service does not use zero-downtime deploys, so a short restart window is expected.
- Only `wp-content/uploads` is writable and persistent. Plugin, theme, and WordPress changes ship through the Docker image.
- Keep the Aiven database and Render service geographically close to reduce backend latency.

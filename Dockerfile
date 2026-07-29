FROM node:22-bookworm-slim AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json vite.config.ts ./
COPY wordpress/wp-content/themes/tradeflow/assets/src ./wordpress/wp-content/themes/tradeflow/assets/src
RUN npm run build

FROM wordpress:cli-php8.3 AS wpcli

FROM wordpress:php8.3-apache

RUN a2enmod expires headers rewrite \
    && a2enconf security

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp
COPY deployment/php.ini /usr/local/etc/php/conf.d/tradeflow.ini
COPY deployment/apache.conf /etc/apache2/conf-available/tradeflow.conf
COPY deployment/render-start.sh /usr/local/bin/tradeflow-start
COPY wordpress/wp-content/plugins/tradeflow-core /usr/src/wordpress/wp-content/plugins/tradeflow-core
COPY wordpress/wp-content/themes/tradeflow /usr/src/wordpress/wp-content/themes/tradeflow
COPY --from=assets /build/wordpress/wp-content/themes/tradeflow/dist /usr/src/wordpress/wp-content/themes/tradeflow/dist

RUN a2enconf tradeflow \
    && chmod 0755 /usr/local/bin/tradeflow-start

WORKDIR /var/www/html

ENTRYPOINT ["tradeflow-start"]
CMD ["apache2-foreground"]

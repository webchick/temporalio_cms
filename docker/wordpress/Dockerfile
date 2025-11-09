FROM wordpress:6.5-apache

ENV WP_CLI_ALLOW_ROOT=1

RUN apt-get update \
    && apt-get install -y \
        curl \
        less \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp \
    && echo "memory_limit=256M" > /usr/local/etc/php/conf.d/wordpress-memory.ini

COPY wordpress/wp-temporal-cms /var/www/html/wp-content/plugins/wp-temporal-cms

COPY wordpress/entrypoint.sh /usr/local/bin/wp-entrypoint.sh
RUN chmod +x /usr/local/bin/wp-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/wp-entrypoint.sh"]

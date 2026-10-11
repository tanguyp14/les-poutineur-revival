FROM wordpress:6-php8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip curl less default-mysql-client \
 && rm -rf /var/lib/apt/lists/* \
 && curl -fsSL -o /usr/local/bin/wp https://github.com/wp-cli/wp-cli/releases/download/v2.11.0/wp-cli-2.11.0.phar \
 && chmod +x /usr/local/bin/wp

# Extensions tierces (dans le cœur WordPress copié au démarrage).
RUN set -eux; cd /usr/src/wordpress/wp-content/plugins; \
    for p in woocommerce advanced-custom-fields; do \
      curl -fsSL -o "$p.zip" "https://downloads.wordpress.org/plugin/$p.latest-stable.zip"; \
      unzip -q "$p.zip"; rm "$p.zip"; \
    done

# Notre code.
COPY wp-content/themes/poutineurs /usr/src/wordpress/wp-content/themes/poutineurs
COPY wp-content/plugins/poutine-builder /usr/src/wordpress/wp-content/plugins/poutine-builder
COPY docker/entrypoint.sh docker/install.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/install.sh \
 && printf 'upload_max_filesize=32M\npost_max_size=32M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/poutineurs.ini

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

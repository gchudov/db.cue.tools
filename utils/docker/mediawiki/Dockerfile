FROM mediawiki:1.31

# System dependencies
RUN set -eux; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libpq-dev \
	; \
	rm -rf /var/lib/apt/lists/*


# Install the PHP extensions we need
RUN set -eux; \
	\
	docker-php-ext-install -j "$(nproc)" \
                pgsql \
	;

RUN ln -s ../mods-available/rewrite.load /etc/apache2/mods-enabled/
RUN set -eux; \
        cd extensions ; \
	mkdir MobileFrontend ; \
        cd MobileFrontend ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/MobileFrontend/+archive/REL1_31.tar.gz" | tar -zx ; \
        cd .. ; \
	mkdir googleAnalytics ; \
        cd googleAnalytics ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/googleAnalytics/+archive/REL1_31.tar.gz" | tar -zx ; \
        cd .. ; \
        cd .. ; \
	chown -R www-data:www-data extensions
COPY extensions/googleAnalyticsLinks/ /var/www/html/extensions/googleAnalyticsLinks/
COPY favicon.ico robots.txt google098e3cccb92122e1.html .htaccess /var/www/html/

FROM mediawiki:1.39

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

RUN set -eux; \
        cd extensions ; \
	mkdir MobileFrontend ; \
        cd MobileFrontend ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/MobileFrontend/+archive/REL1_39.tar.gz" | tar -zx ; \
        cd .. ; \
        mkdir StopForumSpam ; \
        cd StopForumSpam ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/StopForumSpam/+archive/REL1_39.tar.gz" | tar -zx ; \
        cd .. ; \
	mkdir googleAnalytics ; \
        cd googleAnalytics ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/googleAnalytics/+archive/REL1_39.tar.gz" | tar -zx ; \
        cd .. ; \
	mkdir CheckUser; \
        cd CheckUser ; \
	curl -fsSL "https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/CheckUser/+archive/REL1_39.tar.gz" | tar -zx ; \
        cd .. ; \
        cd .. ; \
	chown -R www-data:www-data extensions

COPY remoteip.conf /etc/apache2/conf-available/
RUN ln -s ../mods-available/remoteip.load /etc/apache2/mods-enabled/ \
    && ln -s ../conf-available/remoteip.conf /etc/apache2/conf-enabled/

COPY extensions/googleAnalyticsLinks/ /var/www/html/extensions/googleAnalyticsLinks/
COPY .htaccess /var/www/html/

FROM postgres:9.6
RUN set -eux; \
        \
        apt-get update; \
        apt-get install -y --no-install-recommends \
                wget python python-psycopg2 ca-certificates \
        ; \
        rm -rf /var/lib/apt/lists/*
ADD https://github.com/lalinsky/mbslave/archive/master.tar.gz /mbslave/
RUN set -eux; \
    tar -xzf /mbslave/master.tar.gz --strip-components=1 -C /mbslave/ ; \
    rm /mbslave/master.tar.gz
COPY mbslave.conf /mbslave/
RUN mkdir -p /mbdumps && chown -R postgres:postgres /mbdumps
COPY docker-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/mbslave/mbslave-sync.py"]

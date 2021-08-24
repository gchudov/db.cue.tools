FROM postgres:12
# https://github.com/lalinsky/mbdata
RUN set -eux; \
        \
        apt-get update; \
        apt-get install -y --no-install-recommends \
                wget python python-pip python-psycopg2 python-setuptools ca-certificates  \
        ; \
        pip install -U wheel ; \
        pip install -U mbdata ; \
        rm -rf /var/lib/apt/lists/*
COPY mbslave.conf /etc/
COPY docker-entrypoint.sh /usr/local/bin/
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["mbslave", "sync"]

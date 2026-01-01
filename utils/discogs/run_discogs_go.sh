#!/bin/sh
# Run the Go version of the discogs converter
# Usage: gunzip -c discogs_releases.xml.gz | ./run_discogs_go.sh
#
# Output files are created in the current directory:
#   discogs_*_sql.gz - PostgreSQL COPY data files
#   discogs_enums_sql.gz - Enum type definitions

# Clean invalid XML characters and run the Go converter
sed -e 's/[\x01-\x08|\x0B|\x0C|\x0E-\x1F]//g' -e 's/33 â[^,}]* RPM/33 ⅓ RPM/g' | \
    "$(dirname "$0")/go/discogs"


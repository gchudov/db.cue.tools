#!/bin/sh
gunzip -c | sed 's/[\x01-\x08|\x0B|\x0C|\x0E-\x1F]//g' | php `dirname $0`/discogs.php | bzip2 -1

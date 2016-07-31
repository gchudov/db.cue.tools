cron { 'freedb':
  ensure  => 'present',
  command => "/opt/ctdb/www/ctdbweb/utils/freedb/request_spot.sh >> /var/log/reqspot 2>&1",
  monthday=> 5,
  hour    => 1,
  minute  => 51,
  target  => 'root',
  user    => 'root',
}

cron { 'discogs':
  ensure  => 'present',
  command => "/opt/ctdb/www/ctdbweb/utils/discogs/request_spot.sh >> /var/log/reqspot 2>&1",
  monthday=> 5,
  hour    => 1,
  minute  => 41,
  target  => 'root',
  user    => 'root',
}

cron { 'mbslave':
  ensure  => 'present',
  command => "/root/mbslave/mbslave-sync.py >> /var/log/mbreplication 2>&1",
  minute  => 15,
  target  => 'root',
  user    => 'root',
}

cron { 'stats':
  ensure  => 'present',
  command => " /usr/bin/psql -U ctdb_user ctdb -f /opt/ctdb/www/ctdbweb/utils/hourly_stats.sql >> /dev/null 2>&1",
  minute  => 01,
  target  => 'root',
  user    => 'root',
}


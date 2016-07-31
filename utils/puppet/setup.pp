$reqs = [ 
  "httpd24", "php56-cli", "php56-xml", "php56-pgsql", "php56-mbstring", "php56-pdo", "php56-pear", "php-pear-XML-Parser", "php-pear-XML-Serializer",
  "mercurial-python27", "git", "augeas", "aws-cli", "python27-psycopg2",
  "automake", "autoconf", "gcc-c++",
  "libcurl-devel", "libxml2-devel", "openssl-devel", "boost-thread",
  "postgresql94-server", "postgresql94-contrib",
  "s3cmd", "sendmail.cf",
]
package { $reqs: ensure => "installed" }

#https://github.com/s3fs-fuse/s3fs-fuse.git

yumrepo { "cuetools":
baseurl => "http://s3.cuetools.net.s3.amazonaws.com/RPMS/",
descr => "Tools for CUETools DB",
enabled => 1,
gpgcheck => 0,
}
package { [ "fuse", "fuse-devel", "libxml++-devel", "s3fuse" ] : ensure => "installed" }
file { "/etc/s3fuse": ensure => directory }
file { "/etc/s3fuse/s3.cuetools.net": ensure => file, content => "service=aws
auth_data=/etc/s3fuse/.auth
bucket_name=s3.cuetools.net" }
file { "/etc/s3fuse/private.cuetools.net": ensure => file, content => "service=aws
auth_data=/etc/s3fuse/.auth
bucket_name=private.cuetools.net" }
file { "/etc/s3fuse/backups.cuetools.net": ensure => file, content => "service=aws
auth_data=/etc/s3fuse/.auth
bucket_name=backups.cuetools.net" }
file { "/mnt/s3.cuetools.net": ensure => directory }
mount { "/mnt/s3.cuetools.net": ensure => present, fstype => "fuse", atboot => true, device => "/usr/bin/s3fuse", options => "defaults,user,allow_other,config=/etc/s3fuse/s3.cuetools.net" }
file { "/mnt/private.cuetools.net": ensure => directory }
mount { "/mnt/private.cuetools.net": ensure => present, fstype => "fuse", atboot => true, device => "/usr/bin/s3fuse", options => "defaults,user,allow_other,config=/etc/s3fuse/private.cuetools.net" }
file { "/mnt/backups.cuetools.net": ensure => directory }
mount { "/mnt/backups.cuetools.net": ensure => present, fstype => "fuse", atboot => true, device => "/usr/bin/s3fuse", options => "defaults,user,allow_other,config=/etc/s3fuse/backups.cuetools.net" }

# rpm -i pgdg-redhat94-9.4-2.noarch.rpm
# yum install pgbouncer

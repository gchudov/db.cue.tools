$reqs = [ 
  "httpd24", "php56-cli", "php56-xml", "php56-pgsql", "php56-mbstring", "php56-pdo", "php56-pear", "php-pear-XML-Parser", "php-pear-XML-Serializer",
  "mercurial-python27", "git", "augeas", "aws-cli", "python27-psycopg2",
  "automake", "autoconf", "gcc-c++",
  "libcurl-devel", "libxml2-devel", "openssl-devel", "boost-thread",
  "postgresql94-server", "postgresql94-contrib",
  "s3cmd", "sendmail.cf",
]
package { $reqs: ensure => "installed" }

yumrepo { "cuetools":
baseurl => "http://s3.cuetools.net.s3.amazonaws.com/RPMS/",
descr => "Tools for CUETools DB",
enabled => 1,
gpgcheck => 0,
}
# rpm -i pgdg-redhat94-9.4-2.noarch.rpm
# yum install pgbouncer

#!/bin/sh
export HOME=/root
cd $HOME
#s3cfg
printf "[s3tools]\nname=Tools for managing Amazon S3 - Simple Storage Service (RHEL_6)\ntype=rpm-md\nbaseurl=http://s3tools.org/repo/RHEL_6/\ngpgcheck=1\ngpgkey=http://s3tools.org/repo/RHEL_6/repodata/repomd.xml.key\nenabled=1" > /etc/yum.repos.d/s3tools.repo
#yum -y install php-pgsql postgresql-server
yum -y install php-cli php-xml s3cmd mercurial gcc make bzip2-devel
yum -y upgrade
sed -i 's/memory_limit = [0-9]*M/memory_limit = 2000M/g' /etc/php.ini
hg clone http://hg.cuetools.net/ctdbweb
make -C ctdbweb/utils/freedb/
s3cmd --no-progress get s3://private.cuetools.net/freedb-complete-`date +%Y%m01`.tar.bz2 - | tar vxjO 2>&1 | ./ctdbweb/utils/freedb/freedb
s3cmd --no-progress --rr put freedb_*.sql.bz2 ./ctdbweb/utils/freedb/*.sql s3://private.cuetools.net/freedb/`date +%Y%m01`/
#shutdown -h now

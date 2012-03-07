usage()
{
cat << EOF
usage: $0 options

This script will request a spot instance for discogs database conversion.

OPTIONS:
   -h      Show this message
   -r      Rerun (skip data dump download)
   -d      Debug (don't shutdown the instance)
   -n      Dry run (print script and exit)
   -p      Maximum price
EOF
}

RERUN=
DEBUG=
PRINT=
PRICE=0.20
while getopts “hrdnp:” OPTION
do
     case $OPTION in
         h)
             usage
             exit 1
             ;;
         r)
             RERUN=1
             ;;
         d)
             DEBUG=1
             ;;
         n)
             PRINT=1
             ;;
         p)
             PRICE=$OPTARG
             ;;
         ?)
             usage
             exit
             ;;
     esac
done

discogs_rel=discogs_`date +%Y%m01`_releases.xml.gz
if [ -z "$RERUN" ]; then
  echo "Downloading $discogs_rel"
  user_agent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.8) Gecko/20100721 Firefox/3.6.8"
  wget -q -U "$user_agent" -O "/tmp/$discogs_rel" "http://www.discogs.com/data/$discogs_rel" || exit $?
  s3cmd --no-progress --rr put "/tmp/$discogs_rel" s3://private.cuetools.net/
  rm "/tmp/$discogs_rel"
fi
UDATA="$( cat <<EOF
#!/bin/sh
DEBUG=$DEBUG
S3CFG=$(gzip -c /root/.s3cfg | base64 -w 0)
export HOME=/root
cd /media/ephemeral0
echo \$S3CFG | base64 -d | gunzip > ~/.s3cfg
printf "[s3tools]\nname=Tools for managing Amazon S3 - Simple Storage Service (RHEL_6)\ntype=rpm-md\nbaseurl=http://s3tools.org/repo/RHEL_6/\ngpgcheck=1\ngpgkey=http://s3tools.org/repo/RHEL_6/repodata/repomd.xml.key\nenabled=1" > /etc/yum.repos.d/s3tools.repo
yum -y install php-cli php-xml php-pgsql postgresql-server postgresql-contrib s3cmd mercurial augeas
#yum -y install http://s3.cuetools.net/RPMS/s3fuse-0.11-1.i386.rpm http://s3.cuetools.net/RPMS/glibmm24-devel-2.22.1-1.el6.i686.rpm http://s3.cuetools.net/RPMS/libsigc%2B%2B20-devel-2.2.4.2-1.el6.i686.rpm http://s3.cuetools.net/RPMS/glibmm24-2.22.1-1.el6.i686.rpm http://s3.cuetools.net/RPMS/libsigc%2B%2B20-2.2.4.2-1.el6.i686.rpm
yum -y upgrade
sed -i 's/memory_limit = [0-9]*M/memory_limit = 2000M/g' /etc/php.ini
service postgresql initdb
sed -i 's/local[ ]*all[ ]*all[ ]*ident/local all all trust/g' /var/lib/pgsql/data/pg_hba.conf
service postgresql start
hg clone https://code.google.com/p/cuetools-database/
s3cmd --no-progress get s3://private.cuetools.net/$discogs_rel - | ./cuetools-database/utils/discogs/run_discogs_converter.sh
./cuetools-database/utils/discogs/create_db.sh
s3cmd --no-progress --rr put discogs.bin s3://private.cuetools.net/discogs/`date +%Y%m01`/
if [ -z "\$DEBUG" ]; then
  shutdown -h now
fi

EOF
)"
if [ -z "$PRINT" ]; then
echo "Requesting instance. PRICE=$PRICE; DEBUG=$DEBUG"
ec2rsi -region us-east-1 ami-9f4082f6 -g sg-b81154d1 -k ec2 -n 1 -p $PRICE -r one-time -t c1.medium --user-data "$UDATA"
else
cat <<EOF
$UDATA
EOF
fi

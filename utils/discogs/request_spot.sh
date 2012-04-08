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
CPFILES=
for cpfile in /root/.s3cfg /etc/s3fuse/.??* /etc/s3fuse/* /etc/yum.repos.d/cuetools.repo
do
  CPFILES=$CPFILES"mkdir -p $(dirname $cpfile); echo $(egrep -v '(^#|^$)' $cpfile | gzip | base64 -w 0) | base64 -d | gunzip > $cpfile; chmod $(stat -c '%a' $cpfile) $cpfile"$'\n'
done
UDATA="$( cat <<EOF
#!/bin/sh
#files begin
$CPFILES#files end
DEBUG=$DEBUG
export HOME=/root
cd /media/ephemeral0
yum -y install postgresql8-server postgresql8-contrib
yum -y --enablerepo=epel install php-cli php-xml php-pgsql s3cmd mercurial augeas fuse s3fuse
#yum -y upgrade
sed -i 's/memory_limit = [0-9]*M/memory_limit = 3000M/g' /etc/php.ini
service postgresql initdb
sed -i 's/local[ ]*all[ ]*all[ ]*ident/local all all trust/g' /var/lib/pgsql/data/pg_hba.conf
service postgresql start
for s3confpath in /etc/s3fuse/*
do
  s3conf=\$(basename \$s3confpath)
  echo "s3fuse /mnt/\$s3conf fuse defaults,noauto,user,allow_other,config=/etc/s3fuse/\$s3conf 0 0" >> /etc/fstab
  mkdir /mnt/\$s3conf; mount /mnt/\$s3conf
done
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
#ec2rsi -region us-east-1 ami-9f4082f6 -g sg-b81154d1 -k ec2 -n 1 -p $PRICE -r one-time -t c1.medium --user-data "$UDATA"
ec2rsi -region us-east-1 ami-f565ba9c -g sg-b81154d1 -k ec2 -n 1 -p $PRICE -r one-time -t m1.medium --user-data "$UDATA"
else
cat <<EOF
$UDATA
EOF
fi

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
IROLE=arn:aws:iam::421792542113:instance-profile/ctdbtask
AMIID=ami-dc6aa9b1 #amzn-ami-hvm-2016.03.3.x86_64-s3
#export EC2_PRIVATE_KEY=~ec2-user/.ec2/pk-7CIWDTIK74TUXOHQZNYW24BHMXG6ABBV.pem
#export EC2_CERT=~ec2-user/.ec2/cert-7CIWDTIK74TUXOHQZNYW24BHMXG6ABBV.pem

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
  wget -nv -U "$user_agent" -O "/tmp/$discogs_rel" "http://discogs-data.s3-us-west-2.amazonaws.com/data/$discogs_rel" || exit $?
  s3cmd --no-progress --rr put "/tmp/$discogs_rel" s3://private.cuetools.net/
  rm "/tmp/$discogs_rel"
fi
CPFILES=
for cpfile in /etc/s3fuse/.??* /etc/s3fuse/* /etc/yum.repos.d/cuetools.repo
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
yum -y install postgresql9-server postgresql9-contrib
yum -y --enablerepo=epel install php-cli php-xml php-pgsql mercurial augeas fuse aws-cli
yum -y --enablerepo=cuetools install s3fuse
#yum -y upgrade
chmod -x /etc/cron.daily/makewhatis.cron
sed -i 's/memory_limit = [0-9]*M/memory_limit = 5500M/g' /etc/php.ini
sed -i 's/PGDATA=.*/PGDATA=\/media\/ephemeral0\/pgsql/g' /etc/rc.d/init.d/postgresql
sed -i 's/PGDATA=.*/PGDATA=\/media\/ephemeral0\/pgsql/g' /etc/sysconfig/pgsql/postgresql
service postgresql initdb
sed -i 's/local[ ]*all[ ]*all[ ]*.*/local all all trust/g' /media/ephemeral0/pgsql/pg_hba.conf
service postgresql start
for s3confpath in /etc/s3fuse/*
do
  s3conf=\$(basename \$s3confpath)
  echo "s3fuse /mnt/\$s3conf fuse defaults,noauto,user,allow_other,config=/etc/s3fuse/\$s3conf 0 0" >> /etc/fstab
  mkdir /mnt/\$s3conf; mount /mnt/\$s3conf
done
hg clone http://hg.code.sf.net/p/cuetoolsnet/dbcode cuetools-database
aws s3 cp s3://private.cuetools.net/$discogs_rel ./discogs.xml.gz
./cuetools-database/utils/discogs/run_discogs_converter.sh < ./discogs.xml.gz
outdir=/mnt/private.cuetools.net/discogs/`date +%Y%m01`/
mkdir \$outdir/
./cuetools-database/utils/discogs/create_db.sh > \$outdir/discogs.log 2>&1
aws s3 cp discogs.bin s3://private.cuetools.net/discogs/`date +%Y%m01`/
if [ -z "\$DEBUG" ]; then
  umount /mnt/private.cuetools.net
  shutdown -h now
fi

EOF
)"
if [ -z "$PRINT" ]; then
echo "Requesting instance. PRICE=$PRICE; DEBUG=$DEBUG"
source /etc/profile.d/aws-apitools-common.sh
$EC2_HOME/bin/ec2-request-spot-instances $AMIID --group "quick-start-1" --iam-profile $IROLE --key ec2 --instance-count 1 --price $PRICE --type one-time --instance-type m3.large --user-data "$UDATA"
else
cat <<EOF
$UDATA
EOF
fi

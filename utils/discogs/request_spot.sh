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
AMIID=ami-0c7d8678e345b414c #amzn-ami-hvm-2018.03.0.20180811-x86_64-ebs

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
discogs_year=`date +%Y`
if [ -z "$RERUN" ]; then
  echo "Downloading $discogs_rel"
  aws s3 cp --quiet --storage-class REDUCED_REDUNDANCY s3://discogs-data/data/$discogs_year/$discogs_rel s3://private.cuetools.net/$discogs_rel || exit $?
#  user_agent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.8) Gecko/20100721 Firefox/3.6.8"
#  wget -nv -U "$user_agent" -O "/opt/ctdb/tmp/$discogs_rel" "http://discogs-data.s3-us-west-2.amazonaws.com/data/$discogs_year/$discogs_rel" || exit $?
#  s3cmd --no-progress --rr put "/opt/ctdb/tmp/$discogs_rel" s3://private.cuetools.net/
#  rm "/opt/ctdb/tmp/$discogs_rel"
fi
CPFILES=
UDATA="$( cat <<EOF
#!/bin/sh
#files begin
$CPFILES#files end
DEBUG=$DEBUG
export HOME=/root
mkfs.ext4 /dev/nvme1n1
mkdir -p /media/ephemeral0
mount /dev/nvme1n1 /media/ephemeral0
cd /media/ephemeral0
yum -y install postgresql9-server postgresql9-contrib
yum -y --enablerepo=epel install php-cli php-xml php-pgsql git augeas aws-cli
#yum -y upgrade
chmod -x /etc/cron.daily/makewhatis.cron
sed -i 's/memory_limit = [0-9]*M/memory_limit = 12000M/g' /etc/php.ini
sed -i 's/PGDATA=.*/PGDATA=\/media\/ephemeral0\/pgsql/g' /etc/rc.d/init.d/postgresql
sed -i 's/PGDATA=.*/PGDATA=\/media\/ephemeral0\/pgsql/g' /etc/sysconfig/pgsql/postgresql
service postgresql initdb
sed -i 's/local[ ]*all[ ]*all[ ]*.*/local all all trust/g' /media/ephemeral0/pgsql/pg_hba.conf
service postgresql start
git clone https://github.com/gchudov/db.cue.tools.git cuetools-database
aws s3 cp --quiet s3://private.cuetools.net/$discogs_rel ./discogs.xml.gz
./cuetools-database/utils/discogs/run_discogs_converter.sh < ./discogs.xml.gz
./cuetools-database/utils/discogs/create_db.sh > discogs.log 2>&1
aws s3 cp --quiet discogs.log s3://private.cuetools.net/discogs/`date +%Y%m01`/
aws s3 cp --quiet discogs.bin s3://private.cuetools.net/discogs/`date +%Y%m01`/
if [ -z "\$DEBUG" ]; then
  shutdown -h now
fi

EOF
)"
if [ -z "$PRINT" ]; then
echo "Requesting instance. PRICE=$PRICE; DEBUG=$DEBUG"
source /etc/profile.d/aws-apitools-common.sh
$EC2_HOME/bin/ec2-request-spot-instances $AMIID --network-attachment :0:subnet-0e728857::10.0.0.55:sg-5d2f8a3a:true --associate-public-ip-address true --iam-profile $IROLE --key ec2 --instance-count 1 --price $PRICE --type one-time --instance-type r5d.large --user-data "$UDATA"
#aws ec2 request-spot-instances --region us-east-1 --block-duration-minutes 360 --spot-price $PRICE --type one-time 
aws ec2 describe-spot-instance-requests --region us-east-1
else
cat <<EOF
$UDATA
EOF
fi

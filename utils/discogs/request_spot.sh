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
PRICE=0.30
IROLE=arn:aws:iam::421792542113:instance-profile/ctdbtask
AMIID=ami-01d425805aef71788 #amzn2-ami-hvm-2.0.20211005.0-x86_64-ebs
ITYPE=r5d.xlarge
# r7gd.large

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
  if [ -n "$DEBUG" ]; then
    aws s3 cp --metadata-directive REPLACE --storage-class REDUCED_REDUNDANCY s3://discogs-data-dumps/data/$discogs_year/$discogs_rel s3://private.cuetools.net/$discogs_rel || exit $?
  else
    aws s3 cp --quiet --metadata-directive REPLACE --storage-class REDUCED_REDUNDANCY s3://discogs-data-dumps/data/$discogs_year/$discogs_rel s3://private.cuetools.net/$discogs_rel || exit $?
  fi
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
yum -y install postgresql-server postgresql-contrib
yum -y install php-cli php-xml php-pgsql git augeas aws-cli golang
#yum -y upgrade
chmod -x /etc/cron.daily/makewhatis.cron
sed -i 's/memory_limit = [0-9]*M/memory_limit = 24000M/g' /etc/php.ini
sed -i 's/PGDATA=.*/PGDATA=\/media\/ephemeral0\/pgsql/g' /usr/lib/systemd/system/postgresql.service
mkdir /media/ephemeral0/pgsql
chown postgres.postgres /media/ephemeral0/pgsql
postgresql-setup initdb
sed -i 's/local[ ]*all[ ]*all[ ]*.*/local all all trust/g' /media/ephemeral0/pgsql/pg_hba.conf
service postgresql start
git clone https://github.com/gchudov/db.cue.tools.git cuetools-database
cd ./cuetools-database/utils/discogs
go build -o discogs .
cd /media/ephemeral0
aws s3 cp --quiet s3://private.cuetools.net/$discogs_rel ./discogs_releases.xml.gz
gunzip -c discogs_releases.xml.gz | ./cuetools-database/utils/discogs/run_discogs_go.sh
./cuetools-database/utils/discogs/create_db.sh > discogs.log 2>&1
aws s3 cp --quiet discogs.log s3://private.cuetools.net/discogs/`date +%Y%m01`/
aws s3 cp --quiet discogs.bin s3://private.cuetools.net/discogs/`date +%Y%m01`/
if [ -z "\$DEBUG" ]; then
  shutdown -h now
fi

EOF
)"
UDATA64=$(base64 -w 0 <<< "$UDATA");
LAUNCH_SPEC="$( cat <<EOF
{
  "IamInstanceProfile": { "Arn": "$IROLE" },
  "ImageId": "$AMIID",
  "InstanceType": "$ITYPE",
  "KeyName" : "ec2",
  "NetworkInterfaces": [
    {
      "DeviceIndex": 0,
      "SubnetId": "subnet-0e728857",
      "Groups": [ "sg-5d2f8a3a" ],
      "AssociatePublicIpAddress": true
    }
  ],
  "UserData": "$UDATA64"
}
EOF
)"
if [ -z "$PRINT" ]; then
echo "Requesting instance. PRICE=$PRICE; DEBUG=$DEBUG"
aws ec2 request-spot-instances --region us-east-1 --spot-price $PRICE --type one-time --launch-specification "$LAUNCH_SPEC"
aws ec2 describe-spot-instance-requests --region us-east-1
else
cat <<EOF
UDATA:$UDATA
LAUNCH_SPEC:$LAUNCH_SPEC
EOF
fi

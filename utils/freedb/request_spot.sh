usage()
{
cat << EOF
usage: $0 options

This script will request a spot instance for freedb database conversion.

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

freedb_rel=freedb-complete-`date +%Y%m01`.tar.bz2
if [ -z "$RERUN" ]; then
  echo "Downloading $freedb_rel"
  user_agent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.8) Gecko/20100721 Firefox/3.6.8"
  wget -nv -U "$user_agent" -O "/opt/ctdb/tmp/$freedb_rel" "http://ftp.freedb.org/pub/freedb/$freedb_rel" || exit $?
  aws s3 cp --quiet --storage-class REDUCED_REDUNDANCY "/opt/ctdb/tmp/$freedb_rel" s3://private.cuetools.net/$freedb_rel || exit $?
  rm "/opt/ctdb/tmp/$freedb_rel"
fi
UDATA="$( cat <<EOF
#!/bin/sh
DEBUG=$DEBUG
S3CFG=$(gzip -c ~/.s3cfg | base64 -w 0)
export HOME=/root
mkfs.ext4 /dev/nvme1n1
mkdir -p /media/ephemeral0
mount /dev/nvme1n1 /media/ephemeral0
cd /media/ephemeral0
echo \$S3CFG | base64 -d | gunzip > ~/.s3cfg
printf "[s3tools]\nname=Tools for managing Amazon S3 - Simple Storage Service (RHEL_6)\ntype=rpm-md\nbaseurl=http://s3tools.org/repo/RHEL_6/\ngpgcheck=1\ngpgkey=http://s3tools.org/repo/RHEL_6/repodata/repomd.xml.key\nenabled=1" > /etc/yum.repos.d/s3tools.repo
yum -y install postgresql8-server postgresql8-contrib
yum -y install php-cli php-xml php-pgsql s3cmd git augeas gcc make bzip2-devel
#yum -y upgrade
sed -i 's/memory_limit = [0-9]*M/memory_limit = 2000M/g' /etc/php.ini
service postgresql initdb
sed -i 's/local[ ]*all[ ]*all[ ]*ident/local all all trust/g' /var/lib/pgsql/data/pg_hba.conf
service postgresql start
git clone https://github.com/gchudov/db.cue.tools.git cuetools-database
make -C cuetools-database/utils/freedb/
update-alternatives --set python /usr/bin/python2.6
s3cmd --no-progress get s3://private.cuetools.net/$freedb_rel - | tar vxjO 2>&1 | ./cuetools-database/utils/freedb/freedb 2> freedb.log
s3cmd --no-progress --rr put freedb_*.sql.bz2 ./cuetools-database/utils/freedb/*.sql /var/log/cloud-init*.log freedb.log s3://private.cuetools.net/freedb/`date +%Y%m01`/
if [ -z "\$DEBUG" ]; then
  shutdown -h now
fi

EOF
)"
if [ -z "$PRINT" ]; then
echo "Requesting instance. PRICE=$PRICE; DEBUG=$DEBUG"
#ec2rsi -region us-east-1 ami-9f4082f6 -g sg-b81154d1 -k ec2 -n 1 -p $PRICE -r one-time -t c1.medium --user-data "$UDATA"
source /etc/profile.d/aws-apitools-common.sh
$EC2_HOME/bin/ec2-request-spot-instances -region "us-east-1" $AMIID --network-attachment :0:subnet-0e728857::10.0.0.56:sg-5d2f8a3a:true --associate-public-ip-address true --iam-profile $IROLE --key ec2 --instance-count 1 --price $PRICE --type one-time --instance-type r5d.large --user-data "$UDATA"
else
cat <<EOF
$UDATA
EOF
fi

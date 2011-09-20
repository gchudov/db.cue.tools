freedb_rel=freedb-complete-`date +%Y%m01`.tar.bz2
user_data="/tmp/$(basename $0).$$.tmp"
wget -q -O $freedb_rel http://ftp.freedb.org/pub/freedb/$freedb_rel
s3cmd --no-progress --rr put $freedb_rel s3://private.cuetools.net/
rm $freedb_rel 
cat $(dirname $0)/ec2init.sh | sed "/^#s3cfg/a echo $(gzip -c ~/.s3cfg | base64 -w 0) | base64 -d | gunzip > ~/.s3cfg" > $user_data
ec2rsi -region us-east-1 ami-2a1fec43 -g sg-b81154d1 -k ec2 -n 1 -p 0.20 -r one-time -t c1.medium -f $user_data
rm $user_data

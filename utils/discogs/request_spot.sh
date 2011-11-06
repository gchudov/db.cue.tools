discogs_rel=discogs_`date +%Y%m01`_releases.xml.gz
user_agent="Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.8) Gecko/20100721 Firefox/3.6.8"
user_data="/tmp/$(basename $0).$$.tmp"
wget -q -U $user_agent -O $discogs_rel http://www.discogs.com/data/$discogs_rel
s3cmd --no-progress --rr put $discogs_rel s3://private.cuetools.net/
rm $discogs_rel 
cat $(dirname $0)/ec2init.sh | sed "/^#s3cfg/a echo $(gzip -c ~/.s3cfg | base64 -w 0) | base64 -d | gunzip > ~/.s3cfg" > $user_data
ec2rsi -region us-east-1 ami-9f4082f6 -g sg-b81154d1 -k ec2 -n 1 -p 0.20 -r one-time -t c1.medium -f $user_data
rm $user_data

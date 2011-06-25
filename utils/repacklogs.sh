logdate=`date +%Y-%m-%d -d yesterday`
rm -rf /tmp/s3logs/
mkdir /tmp/s3logs
s3cmd get --no-progress "s3://private.cuetools.net/s3/E3NDN0NN5JOTB5.$logdate*" /tmp/s3logs/
gzip -d -c /tmp/s3logs/E3NDN0NN5JOTB5.*.gz | egrep -v '^#' | sort | gzip > /tmp/s3logs/access_log.gz
s3cmd put /tmp/s3logs/access_log.gz "s3://private.cuetools.net/s3sorted/access_log.$logdate.gz"
s3cmd del "s3://private.cuetools.net/s3/E3NDN0NN5JOTB5.$logdate*"

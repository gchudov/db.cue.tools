logdate=`date +%Y-%m-%d -d yesterday`
rm -rf /tmp/s3logs/
mkdir /tmp/s3logs
aws s3 cp --quiet "s3://private.cuetools.net/s3/" /tmp/s3logs/ --recursive --exclude "*" --include "E3NDN0NN5JOTB5.$logdate*"
gzip -d -c /tmp/s3logs/E3NDN0NN5JOTB5.*.gz | egrep -v '^#' | sort | gzip > /tmp/s3logs/access_log.gz
aws s3 cp /tmp/s3logs/access_log.gz "s3://private.cuetools.net/s3sorted/access_log.$logdate.gz"
aws s3 rm  "s3://private.cuetools.net/s3/" --recursive --exclude "*" --include "E3NDN0NN5JOTB5.$logdate*"

docker run --name ctdbweb -d --network ct --restart always \
  -v ctparity:/var/www/html/parity \
  -v /var/run/postgresql:/var/run/postgresql \
  ctdbweb

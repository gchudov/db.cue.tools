# sudo docker run -it --rm --name certbot \
#         -v "/etc/letsencrypt:/etc/letsencrypt" \
#         -v "/var/lib/letsencrypt:/var/lib/letsencrypt" \
#         certbot/dns-route53:arm64v8-latest renew --dns-route53-propagation-seconds 30
sudo docker run -it --rm --name certbot \
        -v "/etc/letsencrypt:/etc/letsencrypt" \
        -v "/var/lib/letsencrypt:/var/lib/letsencrypt" \
        certbot/dns-route53:latest renew --dns-route53 --agree-tos
sudo docker exec -t proxy apachectl graceful

docker exec -it ctwiki curl -fsSL https://www.stopforumspam.com/downloads/listed_ip_30_all.zip -o /var/www/blacklist/listed_ip_30_all.zip
docker exec -it ctwiki php /var/www/html/extensions/StopForumSpam/maintenance/updateBlacklist.php


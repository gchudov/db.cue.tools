yum -y install docker
systemctl enable docker
systemctl start docker
docker network create ct
yum -y install tmux
yum -y install git
mkdir -p /var/run/postgresql
chmod 777 /var/run/postgresql
cd /opt
git clone https://github.com/gchudov/db.cue.tools.git
cd /opt/db.cue.tools

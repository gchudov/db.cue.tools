docker run -d --name="logspout" --network ct \
        --volume=/var/run/docker.sock:/var/run/docker.sock \
        gliderlabs/logspout

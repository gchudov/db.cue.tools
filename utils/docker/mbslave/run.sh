#docker run --rm --name mbslave --network ct -v mbdumps:/var/lib/mbdumps mbslave
docker run --rm --name mbslave --network ct --env-file /opt/db.cue.tools/utils/docker/mbslave/mbslave.env mbslave

docker run --name postgres96 --network ct --restart always -v postgres96:/var/lib/postgresql/data -e POSTGRES_HOST_AUTH_METHOD=trust -d postgres:9.6

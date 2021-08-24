docker run --name postgres12 --network ct --restart always -v postgres12:/var/lib/postgresql/data -e POSTGRES_HOST_AUTH_METHOD=trust -d postgres:12

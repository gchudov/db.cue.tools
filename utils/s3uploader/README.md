# S3 Uploader

Go version of `s3.php` - uploads parity files to S3.

## What it does

1. Connects to PostgreSQL database (pgbouncer:6432)
2. Queries `submissions2` table for records with `hasparity=true` and `s3=false`
3. For each batch of 10 records:
   - Checks if local parity file exists at `/var/www/html/parity/{id}`
   - If missing, marks `hasparity=false` in database
   - If exists, uploads to S3 bucket `p.cuetools.net` with public-read ACL
   - Marks `s3=true` in database
   - Deletes local file after successful upload
4. Continues until no more records to process

## Build

```bash
cd utils/s3uploader
go mod tidy
go build -o s3uploader
```

## Run

Requires AWS credentials (via environment, IAM role, or ~/.aws/credentials):

```bash
./s3uploader
```

## Environment

- Database: `ctdb` on `pgbouncer:6432` as `ctdb_user`
- S3 Bucket: `p.cuetools.net` (us-east-1)
- Parity directory: `/var/www/html/parity/`

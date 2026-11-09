-- Migration: Covering index on submissions (entryid, subid)
-- Purpose: Lets /api/updates?artist=... fetch the newest submissions of each matching entry
--          with an index-only scan. With only submissions_entryid_index (entryid), every
--          submission needs a random heap read to get its subid, which makes mid-popularity
--          artists (e.g. "nirvana": 8.7k entries, 125k submissions) take over a minute.
-- Date: 2026-11-09
--
-- Expect ~2 GB on disk and a full read of submissions (~20 GB) while building.
-- CONCURRENTLY avoids blocking submit2.php inserts. It cannot run inside a transaction block:
--   docker exec -i postgres16 psql -U postgres -d ctdb < utils/docker/migrations/004_submissions_entryid_subid_idx.sql
-- If it fails midway, drop the INVALID index and rerun:
--   DROP INDEX CONCURRENTLY IF EXISTS submissions_entryid_subid_idx;
--
-- After verifying, the old single-column index is redundant (same leading column) and can be dropped:
--   DROP INDEX CONCURRENTLY submissions_entryid_index;

CREATE INDEX CONCURRENTLY IF NOT EXISTS submissions_entryid_subid_idx
    ON submissions (entryid, subid);

-- Refresh the visibility map so index-only scans skip heap fetches
-- (submissions has ~2.1M rows inserted since its last vacuum).
VACUUM (ANALYZE) submissions;

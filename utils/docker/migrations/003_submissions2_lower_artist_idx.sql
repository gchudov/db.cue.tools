-- Migration: Add case-insensitive exact-match index on submissions2.artist
-- Purpose: Lets /api/updates?artist=... cheaply count an artist's entries and look them up
--          artist-first, instead of scanning all of submissions newest-first.
--          The existing trigram index (submissions2_artist_idx) is too slow for this
--          on common names (e.g. "Unknown Artist").
-- Date: 2026-11-09
--
-- CONCURRENTLY avoids blocking writes to submissions2 (submit2.php keeps working).
-- It cannot run inside a transaction block, so run with plain psql, e.g.:
--   docker exec -i postgres16 psql -U postgres -d ctdb < utils/docker/migrations/003_submissions2_lower_artist_idx.sql
-- If it fails midway, drop the INVALID index and rerun:
--   DROP INDEX CONCURRENTLY IF EXISTS submissions2_lower_artist_idx;

CREATE INDEX CONCURRENTLY IF NOT EXISTS submissions2_lower_artist_idx
    ON submissions2 (lower(artist));

ANALYZE submissions2;

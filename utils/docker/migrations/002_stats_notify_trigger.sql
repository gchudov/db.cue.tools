-- Migration: Add stats update notification trigger
-- Purpose: Notify connected clients when new submissions arrive
-- Date: 2026-02-02

-- Drop existing trigger/function if they exist (for idempotency)
DROP TRIGGER IF EXISTS stats_update_trigger ON submissions;
DROP FUNCTION IF EXISTS notify_stats_update();

-- Notification function
CREATE OR REPLACE FUNCTION notify_stats_update()
RETURNS TRIGGER AS $$
BEGIN
    PERFORM pg_notify('stats_update', json_build_object(
        'type', 'submission',
        'timestamp', extract(epoch from now())
    )::text);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger on submissions table
CREATE TRIGGER stats_update_trigger
AFTER INSERT ON submissions
FOR EACH ROW
EXECUTE FUNCTION notify_stats_update();

-- Verify trigger was created
SELECT tgname, tgrelid::regclass, tgenabled
FROM pg_trigger
WHERE tgname = 'stats_update_trigger';

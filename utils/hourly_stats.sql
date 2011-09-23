INSERT INTO hourly_stats (SELECT date_trunc('hour', time) as hour, count(NULLIF(agent ilike 'EAC%', false)) eac, count(NULLIF(agent ilike 'CUERipper%', false)) cueripper, count(NULLIF(agent ilike 'CUETools%', false)) cuetools FROM submissions WHERE time > (SELECT max(hour) FROM hourly_stats) GROUP BY hour HAVING date_trunc('hour', time) > (select max(hour) from hourly_stats) AND date_trunc('hour', time) < date_trunc('hour', LOCALTIMESTAMP));

DELETE FROM stats_drives;
INSERT INTO stats_drives (SELECT substring(drivename from '([^ ]*) ') as label, count(*) as cnt FROM submissions WHERE drivename IS NOT NULL GROUP BY label ORDER BY cnt DESC LIMIT 100);
DELETE FROM stats_agents;
INSERT INTO stats_agents (SELECT substring(agent from '([^:(]*)( [(]|:|$)') as label, count(*) as cnt FROM submissions WHERE agent IS NOT NULL GROUP BY label ORDER BY cnt DESC LIMIT 100);
DELETE FROM stats_pregaps;
INSERT INTO stats_pregaps (SELECT substring(trackoffsets from '([^ ]*) ') as label, count(*) as cnt FROM submissions2 WHERE int4(substring(trackoffsets from '([^ ]*) ')) < 450  AND int4(substring(trackoffsets from '([^ ]*) ')) != 0 GROUP BY label ORDER BY cnt DESC LIMIT 100);


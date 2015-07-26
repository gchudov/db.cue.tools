\t on
SELECT e2.id FROM submissions2 e JOIN submissions2 e2 ON e2.trackoffsets=e.trackoffsets WHERE e.subcount > 100 AND e2.subcount<2 AND NOT e2.hasparity LIMIT 1000;

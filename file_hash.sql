
select f.*
FROM file_hash f
JOIN (
	SELECT hash, COUNT(*) cnt
	  FROM file_hash
	  GROUP BY hash
	  HAVING cnt > 1
	  ) AS fj ON f.hash = fj.hash
order by f.hash;

delete from file_hash where 1;

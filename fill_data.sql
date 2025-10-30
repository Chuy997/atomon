USE sensor_data;

-- Calcula cuántos registros: desde 2024-07-01 hasta 2024-09-16, cada 2 min ≈ 55k
-- Creamos una tabla temporal con un contador de 0 hasta 60000
DROP TEMPORARY TABLE IF EXISTS seq;
CREATE TEMPORARY TABLE seq (n INT);

-- Insertamos números del 0 al 60000 (≈ suficiente para cubrir el rango)
INSERT INTO seq (n)
SELECT a.N + b.N*1000 AS n
FROM (
  SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) a
JOIN (
  SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
) b
ON 1=1;

-- Ahora tenemos 100 filas (0–99). Multiplicamos bloques:
INSERT INTO seq (n) SELECT n + 100 FROM seq;
INSERT INTO seq (n) SELECT n + 200 FROM seq;
INSERT INTO seq (n) SELECT n + 400 FROM seq;
INSERT INTO seq (n) SELECT n + 800 FROM seq;
-- Con estas expansiones llegas fácilmente a >60,000 filas

-- Generamos timestamps cada 2 minutos desde el inicio
DROP TEMPORARY TABLE IF EXISTS temp_times;
CREATE TEMPORARY TABLE temp_times (ts TIMESTAMP);

INSERT INTO temp_times (ts)
SELECT DATE_ADD('2025-07-01 00:00:00', INTERVAL n*2 MINUTE)
FROM seq
WHERE DATE_ADD('2025-07-01 00:00:00', INTERVAL n*2 MINUTE) <= '2024-09-16 23:59:59';

-- Insertamos sólo los que faltan
INSERT INTO measurements (timestamp, humidity, temperature)
SELECT 
    t.ts,
    ROUND(55 - 10 * SIN(2 * PI() * HOUR(t.ts)/24) + (RAND() * 2 - 1), 1) AS humidity,
    ROUND(24 + 5 * SIN(2 * PI() * HOUR(t.ts)/24) + (RAND() * 0.5 - 0.25), 1) AS temperature
FROM temp_times t
LEFT JOIN measurements m
       ON t.ts = m.timestamp
WHERE m.id IS NULL;

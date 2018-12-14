SELECT p.osm_id, p.access, p.name, p.amenity, p.way_area, p.surface, p.operator, ST_Area(p.way)
FROM planet_osm_polygon p WHERE p.amenity = 'parking'
ORDER by way_area desc;

-- hranice okresov
SELECT * FROM planet_osm_polygon as p where p.boundary='administrative' and p.admin_level = '8';

-- hranice miest
SELECT * FROM planet_osm_polygon as p where p.boundary='administrative' and p.admin_level = '9';

-- hranice krajov
SELECT * FROM planet_osm_polygon as p where p.boundary='administrative' and p.admin_level = '4';

-- okres trnava
SELECT * FROM planet_osm_polygon as p
where p.boundary='administrative' and p.admin_level = '8' and lower(p.name) like '%trnava%';

SELECT * FROM planet_osm_polygon as p where p.admin_level = '4' and lower(p.name) like 'trnavský kraj';

-- convert
SELECT ST_SRID(way) FROM planet_osm_polygon limit 1;

alter table planet_osm_polygon ADD COLUMN geom_data geometry(Geometry, 4326);
UPDATE planet_osm_polygon set geom_data = ST_Transform(way, 4326);

alter table planet_osm_point ADD COLUMN geom_data geometry(Geometry, 4326);
UPDATE planet_osm_point set geom_data = ST_Transform(way, 4326);

SELECT * from planet_osm_polygon p
WHERE p.amenity = 'parking'
ORDER BY p.geom_data <-> ST_SetSRID(ST_GeomFromText('POINT(17.11092 48.15382)'), 4326) LIMIT 20;

-- select all parking lots in trnava region
WITH okres as (
	SELECT * FROM planet_osm_polygon as t
	where t.boundary='administrative' and t.admin_level = '8' and lower(t.name) like '%trnava%' limit 1
)
SELECT p.geom_data from planet_osm_polygon p
cross join okres
where (ST_WITHIN(p.geom_data, okres.geom_data) and p.amenity = 'parking');

SELECT p.geom_data from planet_osm_polygon p
WHERE p.amenity = 'parking'
AND ST_Distance_Sphere(p.geom_data, ST_SetSRID(ST_GeomFromText('POINT(17.587312 48.372312)'), 4326)) < 1000;
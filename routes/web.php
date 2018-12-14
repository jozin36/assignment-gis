<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

$default_location = '-0.15206 51.49732';

$router->get('/', function () use ($router) {
    return redirect('/page');
});

$router->get('test_query', function () use ($router) {
    $results = DB::select("SELECT * FROM planet_osm_polygon t WHERE t.name = 'Bratislava'");
    return $results;
});

function parse_geo_json($data){
    $return_val = array();

    foreach ($data as $value){
        unset($value->way);
        unset($value->geom_data);

        $geo_json = json_decode($value->st_asgeojson);
        unset($value->st_asgeojson);
        $return_val[] = array(
            'type' => 'Feature',
            'geometry' => $geo_json,
            'properties' => (array)$value
        );
    }

    return $return_val;
}

// returns first N nearest parking lots relative to given location
$router->get('nearest', function (Request $request) use ($router, $default_location) {
    $point = $request->input('location', $default_location);
    $limit = $request->input('limit', 10);

    $query = "SELECT p.*, st_asgeojson(p.geom_data) from planet_osm_polygon p 
              WHERE p.amenity = 'parking' 
              ORDER BY p.geom_data <-> ST_SetSRID(ST_GeomFromText('POINT($point)'), 4326) LIMIT $limit";

    $results = DB::select($query);
    return parse_geo_json($results);
});

// return all parking lots within given radius from specific location
// distance unit is meter
$router->get('radius',  function (Request $request) use ($router, $default_location) {
    $distance = $request->input('distance', 1000);
    $point = $request->input('location', $default_location);

    $query = "SELECT p.*, st_asgeojson(p.geom_data) from planet_osm_polygon p 
              WHERE p.amenity = 'parking' 
              AND ST_DistanceSphere(p.geom_data, ST_SetSRID(ST_GeomFromText('POINT($point)'), 4326)) <= $distance";

    $results = DB::select($query);
    return parse_geo_json($results);
});

// return all parking lots in specific region
$router->get('region',  function (Request $request) use ($router) {
    $id = $request->input('osm_id', "-388234");

    $query = "
                WITH okres as (
	                SELECT * FROM planet_osm_polygon as t
	                where t.osm_id = $id limit 1
                )
                SELECT p.*, st_asgeojson(p.geom_data) from planet_osm_polygon p cross join okres
                where (ST_WITHIN(p.geom_data, okres.geom_data) and p.amenity = 'parking')";

    $results = DB::select($query);
    return parse_geo_json($results);
});

// return all parking lots in specific region
$router->get('get_regions',  function (Request $request) use ($router) {

    $query = "SELECT p.osm_id, p.name from planet_osm_polygon p
              where p.boundary='administrative' and p.admin_level = '8' order by p.name";

    $results = DB::select($query);
    return $results;
});

$router->get('get_accidents', function (Request $request) use ($router, $default_location){

    $query = "SELECT longitude, latitude, Number_of_Casualties from car_accidents 
              WHERE ST_DistanceSphere(geom_data, ST_SetSRID(ST_GeomFromText('POINT($default_location)'), 4326)) < 30000";


    $query = "
        SELECT
        kmeans_cid,
        count(*), st_asgeojson(ST_Centroid(ST_Collect(geom_data)))
        FROM (
            SELECT
            ST_ClusterKMeans(geom_data, 2000) OVER () kmeans_cid,
            geom_data
            FROM accidents
        ) kmeans GROUP BY kmeans_cid;
    ";

    $results = DB::select($query);
    return parse_geo_json($results);
});

$router->get('save_parking', function (Request $request) use ($router, $default_location){

    $query = "
    with accidents as (
	SELECT * from car_accidents  
	WHERE ST_DistanceSphere(geom_data, ST_SetSRID(ST_GeomFromText('POINT(-0.15206 51.49732)'), 4326)) <= 1000
    ) 
    SELECT st_asgeojson(p.geom_data), p.osm_id, count(c.gid) as pocet from planet_osm_polygon p 
    LEFT JOIN accidents c on (ST_DistanceSphere(p.geom_data, c.geom_data) < 50)
    WHERE p.amenity = 'parking' and ST_DistanceSphere(p.geom_data, ST_SetSRID(ST_GeomFromText('POINT(-0.15206 51.49732)'), 4326)) <= 1000
    group by p.geom_data, p.osm_id order by pocet asc limit 10;
    ";

    $results = DB::select($query);
    return parse_geo_json($results);
});
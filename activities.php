<?php
require 'vendor/autoload.php';
use GeoIp2\Database\Reader;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$reader = new Reader('GeoLite2-City.mmdb');

if(isset($_REQUEST['user_id'])){
    $user_id = intval($_REQUEST['user_id']);
}else{
    http_response_code(400);
    exit("user_id required");
}



$db = pg_connect($_ENV['RAILGUN_DATABASE']) or die("Can't connect to database" . pg_last_error());
$data = [];
$result = pg_query_params($db, "SELECT started_at, ended_at, uplink_traffic + downlink_traffic AS traffic, 1 AS service, protocol, client_address FROM ports.traffic RIGHT JOIN network.access USING (server_id) INNER JOIN ports.ports ON ports.port = traffic.port AND ports.zone_id = access.zone_id WHERE user_id = $1 AND started_at > now() - '1 month' :: INTERVAL ORDER BY client_address, started_at", [$user_id]) or die("query failed" . pg_last_error($db));
while ($row = pg_fetch_assoc($result)) {
var_dump($result);
    $last_index = count($data) - 1;
    $row['started_at'] = strtotime($row['started_at']);
    $row['ended_at'] = strtotime($row['ended_at']);
    $row['traffic'] = intval($row['traffic']);
    $row['service'] = intval($row['service']);
    $row['protocol'] = intval($row['protocol']);
    if (!empty($data) and $data[$last_index]['client_address'] == $row['client_address'] and $data[$last_index]['service'] == $row['service'] and $data[$last_index]['protocol'] == $row['protocol'] and $data[$last_index]['ended_at'] + 3600 >= $row['ended_at']) {
        $data[$last_index]['traffic'] += $row['traffic'];
        $data[$last_index]['ended_at'] = $row['ended_at'];
    } else {
        $data [] = $row;
    }
}
var_dump($data);
$data = array_values(array_filter($data, function($row){return $row['traffic'] > 5120;}));
$result = pg_query_params($db, "SELECT acctstarttime AS started_at, acctstoptime AS ended_at, acctsessiontime AS duration, acctinputoctets + acctoutputoctets AS traffic, 3 AS service, 5 AS protocol, split_part(callingstationid, '=', 1) AS client_address FROM radius.radacct RIGHT JOIN radius.radcheck USING (username) WHERE id = $1 AND acctstarttime > now() - '1 month' :: INTERVAL", [$user_id]) or die("query failed" . pg_last_error($db));
while ($row = pg_fetch_assoc($result)) {

    if (isset($row['started_at'])) {
        $row['started_at'] = strtotime($row['started_at']);
    }
    if (isset($row['ended_at'])) {
        $row['ended_at'] = strtotime($row['ended_at']);
    }
    if (isset($row['duration'])) {
        $row['duration'] = intval($row['duration']);
    }
    if (isset($row['traffic'])) {
        $row['traffic'] = intval($row['traffic']);
    }
    if (isset($row['service'])) {
        $row['service'] = intval($row['service']);
    }
    if (isset($row['protocol'])) {
        $row['protocol'] = intval($row['protocol']);
    }
    $data[] = $row;
}
$now = time();
foreach ($data as $index => $row) {
    $data[$index]['started_at'] = date('c', $row['started_at']);
    if($row['ended_at']){
        $data[$index]['ended_at'] = date('c', $row['ended_at']);
    }
    try {
        $record = $reader->city($row['client_address']);
        $data[$index]['location'] = $record->city->names['zh-CN'] ?: $record->city->name ?: $record->mostSpecificSubdivision->names['zh-CN'] ?: $record->mostSpecificSubdivision->name ?: $record->country->names['zh-CN'] ?: $record->country->names['zh-CN'];
    } catch (Exception $error){
        
    }
}
/*usort($data, function ($a, $b) {
    if ($a['started_at'] < $b['started_at']) {
        return -1;
    } else if ($a['started_at'] = $b['started_at']) {
        return 0;
    } else {
        return 1;
    }
});*/
header('Content-Type: application/json; charset=UTF8');
echo json_encode($data);

<?php
function calcDist($lat1,$lat2,$long1,$long2){
    $d_lat = $lat1 - $lat2;
    $d_long = $long1 - $long2;
    return $d_lat*$d_lat+$d_long*$d_long;
}
/*interface TukkomiData
{
    $tukkomi_word;
    $tukkomi_img;
    $tukkomi_id;
    $spot_lat;
    $spot_lang;
}
*/

//異なるドメインからのデータ送信を許可
header("Access-Control-Allow-Origin: *");

// MySQLサーバへ接続
try {
	$dns = "mysql:host=localhost; dbname=owarai_ar_db;charset=utf8";
	$user = "root";
	$password = "";
    $pdo = new PDO($dns, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e){
	echo '接続失敗';
	exit();
}

$output = [];

//入力
$lat = isset($_POST["lat"]) ? $_POST["lat"] : 35;
$long = isset($_POST["long"]) ? $_POST["long"] : 135;

// ツッコミを全部取得
$stmt = $pdo->query("SELECT * FROM tukkomi");
// 連想配列を取得
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // ツッコミを全部取得
$stmt2 = $pdo->query("SELECT * FROM spot WHERE id = ${row[id]}");
    $spot = $stmt2->fetch(PDO::FETCH_ASSOC);
    $row = array_merge($row, $spot);


    $dist = array('dist' => calcDist($lat,$spot['latitude'],$long,$spot['longitude']));
    $row = array_merge($row,$dist);

    array_push($output, $row);
}



//入力
$lat = isset($_POST["lat"]) ? $_POST["lat"] : 135;
$long = isset($_POST["long"]) ? $_POST["long"] : 35;

//距離の入れ替え処理
foreach ((array) $output as $key => $value) {
    $sort[$key] = $value['dist'];
}
array_multisort($sort, SORT_ASC, $output);

$output = json_encode($output);
print_r($output);

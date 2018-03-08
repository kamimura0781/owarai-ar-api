<?php
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
    $newRow = array_merge($row, $spot);

    array_push($output, $newRow);
}

print_r($output);

//入力
$lat = isset($_POST["lat"]) ? $_POST["lat"] : 35;
$long = isset($_POST["long"]) ? $_POST["long"] : 135;

//距離の入れ替え処理
for

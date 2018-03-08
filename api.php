<?php
//全ツッコミ情報を取得
function getAllTukkomi($pdo,$lat,$long){
    $output = [];
    $stmt = $pdo->query("SELECT * FROM tukkomi");
    // 連想配列を取得
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmt2 = $pdo->query("SELECT * FROM spot WHERE id = ${row[id]}");
        $spot = $stmt2->fetch(PDO::FETCH_ASSOC);
        $row = array_merge($row, $spot);

        $dist = array('dist' => calcDist($lat,$spot['latitude'],$long,$spot['longitude']));
        $row = array_merge($row,$dist);

        array_push($output, $row);
    }

    return $output;
}

//特定のツッコミ情報を取得
function getTukkomi($pdo,$tukkomi_id){
    $stmt = $pdo->query("SELECT * FROM tukkomi WHERE id=${tukkomi_id}");
    $row = $stmt->fetch(PDO::FETCH_ASSOC); 
    return $row;
}

//ユーザIDに対応するユーザ情報を取得
function getUser($pdo,$user_id){
    $stmt = $pdo->query("SELECT * FROM account WHERE id=${user_id}");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    unset($row['id']);
    return $row;
}

//2点間の距離を計算
//（緯度と経度の差を計算している．また，2乗和のルートではなく2乗和を返す）
function calcDist($lat1,$lat2,$long1,$long2){
    $d_lat = $lat1 - $lat2;
    $d_long = $long1 - $long2;
    return $d_lat*$d_lat+$d_long*$d_long;
}

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

$req = isset($_POST["req"]) ? $_POST["req"] : "tukkomi";

if ($req == "fetch_list")
{
    //位置情報がクライアントから入力される
    $lat = isset($_POST["lat"]) ? $_POST["lat"] : 35;
    $long = isset($_POST["long"]) ? $_POST["long"] : 135;

    // ツッコミを全部取得
    $output = getAllTukkomi($pdo,$lat,$long);

    //距離の入れ替え処理
    foreach ((array) $output as $key => $value) {
        $sort[$key] = $value['dist'];
    }
    array_multisort($sort, SORT_ASC, $output);

    //クライアントに全ツッコミ情報を返す
    echo json_encode($output);
}else if($req == "tukkomi"){
    //クライアントからツッコミIDが入力される
    $tukkomi_id = isset($_POST["tukkomi_id"]) ? $_POST["tukkomi_id"] : 2;
    
    // ツッコミIDに対応するツッコミ情報を取得
    $tukkomi = getTukkomi($pdo,$tukkomi_id);

    //ツッコミに対応するユーザ情報を取得
    $user = getUser($pdo,$tukkomi['userId']);

    //ツッコミ情報とユーザ情報を結合
    $output = array_merge($tukkomi,$user);

    //クライアントにツッコミ情報を返す
    echo json_encode($output);
}
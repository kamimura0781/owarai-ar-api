<?php
//
//クライアントからの入力部分は，デバッグが終われば必ず訂正する！
//

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

//ツッコミ情報を追加
function addTukkomi($pdo,$userId,$content,$photoId,$img,$spotId,$spot_lat,$spot_long){
    $id = genTukkomiId();

    try{
        if($spotId == null){    //spotIdがなければ，新規スポットとしてツッコミを登録
            //スポットIDを生成
            $spotId = genSpotId();
            
            //新規スポットをspotテーブルに登録
            $pdo->beginTransaction();
            $sql = "INSERT INTO spot (id,latitude,longitude) VALUES (${spotId},${spot_lat},${spot_long}";
            $sth = $pdo->prepare($sql);
            $sth->execute();
            $pdo->commit();

            //写真IDを生成
            $photoId = genPhotoId();

            //写真をサーバのフォルダにおく
            writePhoto($img,$photoId);
        }
        //既存スポットとしてツッコミを登録
        $pdo->beginTransaction();
        $sql = "INSERT INTO tukkomi (id,userId,content,likes,photoId,spotId) VALUES (${id},${userId},${content},0,${photoId},${spotId}";
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $pdo->commit();
        return true;
    }catch(PDOException $e){
        return false;
    }
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

//リクエストをクライアントから受け取る
$req = isset($_POST["req"]) ? $_POST["req"] : "tukkomi";

if ($req == "fetch_list"){
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
}else if($req == "fetch_same"){
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
}else if($req == "add_tukkomi"){
    //追加するツッコミ情報がクライアントから入力される
    $spot_id = isset($_POST["spot_id"]) ? $_POST["spot_id"] : 1;
    $spot_lat = isset($_POST["spot_lat"]) ? $_POST["spot_lat"] : null;
    $spot_long = isset($_POST["spot_long"]) ? $_POST["spot_long"] : null;
    $img = isset($_POST["img"]) ? $_POST["img"] : null;
    $img_id = isset($_POST["img_id"]) ? $_POST["img_id"] : 0;
    $tukkomi_word = isset($_POST["tukkomi_word"]) ? $_POST["tukkomi_word"] : "";
    $user_id = isset($_POST["user_id"]) ? $_POST["user_id"] : 1;

    //もし新規画像ファイルが送られてきたなら，base64形式からバイナリ形式に変換する
    if($img != null){
        $img = base64_decode($img);
    }

    //ツッコミを追加
    if(addTukkomi($user_id,$tukkomi_word,$photoId,$spot_id,$spot_lat,$spot_long)){
        echo '登録成功';
    }else{
        $pdo->rollBack();
    	echo '登録失敗';
    	exit();
    }
}
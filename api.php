<?php
//
//クライアントからの入力部分は，デバッグが終われば必ず訂正する！
//

/*
 * 全ツッコミ情報を取得
 */
function getAllTukkomi($pdo,$lat,$long)
{
    $output = [];
    $stmt = $pdo->query("SELECT * FROM tukkomi");

    // 連想配列を取得
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sth2 = $pdo->prepare("SELECT * FROM spot WHERE id = :id");
        $sth2->bindValue(':id', $row['spotId']);
        $sth2->execute();
        $spot = $sth2->fetch(PDO::FETCH_ASSOC);
        $row = array_merge($row, $spot);

        $dist = array('dist' => calcDist($lat,$spot['latitude'],$long,$spot['longitude']));
        $row = array_merge($row,$dist);

        //出力形式を整える
        $row['tukkomi_word']  = $row['content'];
        $row['tukkomi_img'] = $row['photoId'];
        $row['tukkomi_id'] = $row['id'];
        $row['spot_id'] = $row['spotId'];
        $row['spot_lat'] = $spot['latitude'];
        $row['spot_long'] = $spot['longitude'];
        
        array_push($output, $row);
    }

    return $output;
}

/*
 * 特定のツッコミ情報を取得
 */
function getTukkomi($pdo,$tukkomi_id)
{
    $stmt = $pdo->query("SELECT * FROM tukkomi WHERE id=${tukkomi_id}");
    $row = $stmt->fetch(PDO::FETCH_ASSOC); 
    $row['tukkomi'] = $row['content'];
    $row['tukkomi_img'] = $row['photoId'];
    $row['like'] = $row['likes'];
    return $row;
}

/*
 * ユーザIDに対応するユーザ情報を取得
 */
function getUser($pdo, $user_id)
{
    $sth = $pdo->prepare("SELECT * FROM account WHERE id = :id");
    $sth->bindValue(':id', $user_id);
    $sth->execute();
    $row = $sth->fetch(PDO::FETCH_ASSOC);
    unset($row['id']);
    unset($row['mail_address']);
    unset($row['password']);
    $row['user_name'] = $row['name'];
    $row['user_img'] = $row['img'];
    $row['user_bio'] = $row['bio'];
    return $row;
}

/*
 * ツッコミ情報を追加
 */
function addTukkomi($pdo, $input_param)
{
    //変数を格納
    /*
    foreach ($input_param as $key => $value)
    {
        $$key = $value;
    }
    */
    $userId  = $input_param["user_id"];
    $content = $input_param["tukkomi_word"];
    $photoId = $input_param["img_id"];
    $img     = $input_param["img"];
    $spotId  = $input_param["spot_id"];
    $spot_lat  = $input_param["spot_lat"];
    $spot_long = $input_param["spot_long"];

    /*
     * spotIdが無ければ、新スポットとして、新しいスポットと写真を登録
     */
    if($spotId == null)
    {
        try
        {
            //新規スポットをspotテーブルに登録
            $pdo->beginTransaction();
            $sql = "INSERT INTO spot (latitude,longitude) VALUES (:lat, :lng)";
            $sth = $pdo->prepare($sql);
            $sth->bindValue(':lat', $spot_lat);
            $sth->bindValue(':lng', $spot_long);
            $sth->execute();
            $pdo->commit();

            //写真をサーバのフォルダにおく(ファイル名は写真ID)
            $img     = base64_decode($img);
            $photoId = genPhotoId();
            file_put_contents("./img/" . $photoId . ".png", $img);
        }
        catch(PDOException $e)
        {
            print_r($e);
            $pdo->rollBack();
            return false;
        }

        // 最新のスポットidを取得
        try
        {
            $stmt = $pdo->query("SELECT MAX(id) FROM spot");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $spotId = $row['MAX(id)'];
        }
        catch(PDOException $e)
        {
            return false;
        }
    }
    
    /*
     * ツッコミ登録
     */
    try
    {
        $pdo->beginTransaction();
        $sql = "INSERT INTO tukkomi (userId,content,likes,photoId,spotId) VALUES (:userId,:content,:likes,:photoId,:spotId)";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':userId', $userId);
        $sth->bindValue(':content', $content);
        $sth->bindValue(':likes', 0);
        $sth->bindValue(':photoId', $photoId);
        $sth->bindValue(':spotId', $spotId);
        $sth->execute();
        $pdo->commit();

        return true;
    }
    catch(PDOException $e)
    {
        print_r($e);
        $pdo->rollBack();
        return false;
    }
}

/*
 * 写真のidを生成（ランダム）
 */
function genPhotoId($length = 8)
{
    $seed = '1234567890abcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($seed), 0, $length);
}

/*
 * 2点間の距離を計算
 *（緯度と経度の差を計算している．また，2乗和のルートではなく2乗和を返す）
 */
function calcDist($lat1, $lat2, $long1, $long2)
{
    $d_lat = $lat1 - $lat2;
    $d_long = $long1 - $long2;

    return sqrt(pow($d_lat, 2) + pow($d_long, 2));
}

/* ----------------------------------
 * メイン処理
 * ---------------------------------- */

/*
 * 異なるドメインからのデータ送信を許可
 */
header("Access-Control-Allow-Origin: *");

/*
 * MySQLサーバへ接続
 */
// 接続情報の準備
$hostname = 'localhost';
$dbname   = 'owarai_ar_db';
$user = "root";
$password = "";

// 接続処理
try
{
	$dns = "mysql:host=$hostname; dbname=$dbname;charset=utf8";
    $pdo = new PDO($dns, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch(PDOException $e)
{
	echo '接続失敗';
	exit();
}

/*
 * リクエストに応じた処理
 */

// リクエストをクライアントから受け取る
$req = isset($_POST["req"]) ? $_POST["req"] : "add_like";

/*
 * ツッコミの一覧を取得
 * 
 * 与えるもの
 * lat  : 現在位置の緯度
 * long : 現在位置の経度
 * 
 * 返ってくるもの(以下の情報をまとめた配列のJSON)
    [tukkomi_id] => ツッコミid
    [tukkomi_word] => ツッコミの言葉
    [likes] => likeの数
    [tukkomi_img] => 写真のid
    [spotId] => 場所のid
    [userId] => ツッコミしたユーザーid
    [spot_lat] => ツッコミ現場の緯度
    [spot_long] => ツッコミ現場の経度
    [dist] => ツッコミ現場と現在位置の距離
*/
if ($req == "fetch_list")
{
    //位置情報がクライアントから入力される
    $lat = isset($_POST["lat"]) ? $_POST["lat"] : 35;
    $long = isset($_POST["long"]) ? $_POST["long"] : 135;

    // ツッコミを全部取得
    $output = getAllTukkomi($pdo, $lat, $long);

    //距離の入れ替え処理
    foreach ((array) $output as $key => $value)
    {
        $sort[$key] = $value['dist'];
    }
    if (count($output) > 0)
    {
        array_multisort($sort, SORT_ASC, $output);
    }

    //クライアントに全ツッコミ情報を返す
    echo json_encode($output);

    exit();
}

/*
 * ツッコミの詳細を表示
 * 
 * 与えるもの
 * tukkomi_id: ツッコミのid
 * 
    [id] :id
    [tukkomi]  ツッコミの言葉
    [like]    likeの数
    [tukkomi_img]  写真のid
    [spotId]   場所のid
    [userId]   ツッコミをしたユーザーのid
    [user_name]     ツッコミをしたユーザーの名前
    [user_bio]      ツッコミをしたユーザーのプロフィール
    [user_img]      ツッコミをしたユーザーの画像
    [iconId]   ツッコミをしたユーザーのアイコンid
 */
if($req == "tukkomi")
{
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

    exit();
}

/*
 * 同じ対象物と思われるツッコミを表示
 * 
 * 与えるもの
 * lat  : 緯度
 * long : 経度
 * 
 * 返ってくるもの(これの配列が近い順に)
    [id]ツッコミid
    [tukkomi]ツッコミの言葉
    [like]likeの数
    [tukkomi_img]写真のid
    [spotId]場所のid
    [userId]ユーザーのid
    [user_name]ユーザの名前
    [user_img]ユーザの画像
    [user_bio]ユーザのプロフィール
    [latitude]現場の緯度
    [longitude]現場の経度
    [dist]現場と現在位置の間の距離
 */
if($req == "fetch_same")
{
    //位置情報がクライアントから入力される
    $lat  = isset($_POST["lat"]) ? $_POST["lat"] : 35;
    $long = isset($_POST["long"]) ? $_POST["long"] : 135;

    // ツッコミを全部取得
    $output = getAllTukkomi($pdo,$lat,$long);

    //距離の入れ替え処理
    foreach ((array) $output as $key => $value)
    {
        $sort[$key] = $value['dist'];
    }
    array_multisort($sort, SORT_ASC, $output);

    echo "<pre>";
    print_r($output);
    echo "</pre>";

    //クライアントに全ツッコミ情報を返す
    echo json_encode($output);

    exit();
}

// likeを加える
if($req == "add_like")
{
    //クライアントからツッコミIDが入力される
    $tukkomi_id = isset($_POST["tukkomi_id"]) ? $_POST["tukkomi_id"] : 2;
    //ユーザーのid
    $user_id    = isset($_POST["user_id"])    ? $_POST["user_id"]    : 1;

    // 既に同一ユーザーが同一ツッコミにlikeしていないか確認
    try
    {
        $sql = "SELECT * FROM likes WHERE userId = :userId AND tukkomiId = :tukkomiId";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':userId', $user_id);
        $sth->bindValue(':tukkomiId', $tukkomi_id);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_OBJ);

        if (count($result) > 0)
        {
            echo "既に登録しています。";
            exit;
        }
    }
    catch(PDOException $e)
    {
        echo "登録失敗";
        exit;
    }


    // データベースのlikesテーブルへ登録
    try
    {
        $pdo->beginTransaction();
        $sql = "INSERT INTO likes (userId, tukkomiId) VALUES (:userId, :tukkomiId)";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':userId', $user_id);
        $sth->bindValue(':tukkomiId', $tukkomi_id);
        $sth->execute();
        $pdo->commit();
        echo "登録完了";
    }
    catch(PDOException $e)
    {
        $pdo->rollBack();
        echo "登録失敗";
    }

    // データベースのtukkomiテーブルでlikesを加算
    try
    {
        $pdo->beginTransaction();
        $sql = "UPDATE tukkomi SET likes = likes + 1 WHERE id = :tukkomiId;";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':tukkomiId', $tukkomi_id);
        $sth->execute();
        $pdo->commit();
        echo "登録完了";
    }
    catch(PDOException $e)
    {
        $pdo->rollBack();
        echo "登録失敗";
    }
}

/*
 * ツッコミを加える
 * 
 * spot_id      :場所id(既存の場所の場合)
 * spot_lat     :緯度(新しい場所の場合)
 * spot_long    :経度(新しい場所の場合)
 * img          :画像データ(新しい場所の場合)
 * img_id       :画像データ(既存の場所の場合)
 * tukkomi_word :ツッコミの言葉
 * user_id      :ツッコミをかました人のid
 */
if($req == "add_tukkomi")
{
    //追加するツッコミ情報がクライアントから入力される
    $input_param = array(
        "spot_id"   => isset($_POST["spot_id"])   ? $_POST["spot_id"]   :null,
        "spot_lat"  => isset($_POST["spot_lat"])  ? $_POST["spot_lat"]  : 99,
        "spot_long" => isset($_POST["spot_long"]) ? $_POST["spot_long"] : 35,
        "img"       => isset($_POST["img"])       ? $_POST["img"]       : null,
        "img_id"    => isset($_POST["img_id"])    ? $_POST["img_id"]    : 0,
        "tukkomi_word" => isset($_POST["tukkomi_word"]) ? $_POST["tukkomi_word"] : "なんでやねん！",
        "user_id"   => isset($_POST["user_id"])   ? $_POST["user_id"]   : 1
    );
    //デバック用(base64で届かないとき)
    //$input_param["img"] = base64_encode(file_get_contents($_FILES["img"]["tmp_name"]));

    //ツッコミを追加
    if(addTukkomi($pdo,$input_param))
    {
        echo '登録成功';
    }else{
    	echo '登録失敗';
    }

    exit();
}
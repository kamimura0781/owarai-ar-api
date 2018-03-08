<?php
//異なるドメインからのデータ送信を許可
header("Access-Control-Allow-Origin: *");

// MySQLサーバへ接続
try {
	$dns = "mysql:host=ホストネーム; dbname=好きなデータベースの名前;charset=utf8";
	$user = "ユーザー名";
	$password = "パスワード";
    $pdo = new PDO($dns, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e){
	echo '接続失敗';
	exit();
}

$time = isset($_POST["time"]) ? $_POST["time"] : "無し";
$x = isset($_POST["x"]) ? $_POST["x"] : "無し";
$y = isset($_POST["y"]) ? $_POST["y"] : "無し";
$z = isset($_POST["z"]) ? $_POST["z"] : "無し";

try{
    // 挿入
	$pdo->beginTransaction();
	$sql = "INSERT INTO acceleration (time, x, y, z, ip) VALUES (:time, :x, :y, :z, :ip) ";
	$sth = $pdo->prepare($sql);
	$sth->bindValue(':time', $time);
	$sth->bindValue(':x', $x);
	$sth->bindValue(':y', $y);
	$sth->bindValue(':z', $z);
	$sth->bindValue(':ip', $_SERVER["REMOTE_ADDR"]);
	$sth->execute();
	$pdo->commit();
	echo '登録成功';
}catch(PDOException $e){
	$pdo->rollBack();
	echo '登録失敗';
	exit();
}
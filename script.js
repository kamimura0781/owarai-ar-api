//あらゆる関数内で使用するグローバル変数たち
var gx = 0; //加速度（重力含む）のx軸
var gy = 0; //加速度（重力含む）のy軸
var gz = 0; //加速度（重力含む）のz軸
var dgt = 1;//取得する値の小数桁
var send_flag = false;//送るかどうかのフラグ
var wait_data = [];//取得した値を一時的に格納
var save_data = [];//蓄積したデータを編集する時に格納

//当ファイルが読み込まれた際に実行される関数
(function(){
	//指定ミリ秒ごとに指定関数を実行
	setInterval("update_data()",1000);
	
	//ローカルストレージにアイテムセット
	if(localStorage.getItem("sensor") == null){
		localStorage.setItem("sensor", '[]');
	}
	
	//加速度の変化を認識する度に実行
	window.addEventListener("devicemotion", function(e){
		//加速度(重力加速度含む)の取得
		var g = e.accelerationIncludingGravity;
		//各成分を指定した小数の桁数で保存
		gx = Number(g.x).toFixed(dgt);
		gy = Number(g.y).toFixed(dgt);
		gz = Number(g.z).toFixed(dgt);
		//HTMLに表示
		document.getElementById('acc-gx').innerHTML = gx;
		document.getElementById('acc-gy').innerHTML = gy;
		document.getElementById('acc-gz').innerHTML = gz;
	});
})();

//定期時間ごとに実行される関数（収集データを一時的に保存、送信）
function update_data(){
	//データを送信（保存）するかをフラグで判定
	if(send_flag){
		//取得したデータを追加
		wait_data.push({
			x: gx,
			y: gy,
			z: gz,
			time: getTime()
		});
		//一時保存のデータが5件以上溜まれば全てサーバーへ送信
		if(wait_data.length >= 5){
			while(wait_data.length > 0){
				//配列の先頭を送信
				send_data(wait_data[0]);
				//配列の先頭を削除
				wait_data.shift();
			}
		}
	}
	//現在の各種件数をHTMLに表示
	showCount();
}

//指定したデータをサーバーへ送信する
function send_data(data){
	//HTMLに通信状況を表示する領域名を指定
	var status_area = document.getElementById('status');
	
	//非同期通信（前回の講義で扱いました）
	var url = '送信先のURL';
	var send_str = encodeURLParm(data);    
    var xhr = new XMLHttpRequest();
    xhr.open("post", url, true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.send(send_str);
	xhr.onreadystatechange = function() {
		switch ( xhr.readyState ) {
		case 0: // 未初期化状態.
			status_area.innerHTML = 'uninitialized!';
			break;
		case 1: // データ送信中.
			status_area.innerHTML = 'loading...';
			break;
		case 2: // 応答待ち.
			status_area.innerHTML = 'loaded.';
			break;
		case 3: // データ受信中.
			status_area.innerHTML = 'interactive... ';
			break;
		case 4: // データ受信完了.
			if(xhr.status == 200 || xhr.status == 304){
				//サーバーから帰ってきたメッセージをHTMLで表示
				status_area.innerHTML = xhr.response;
				//サーバーからのメッセージ的に登録失敗が判った
				if(xhr.response != '登録成功'){
					//登録失敗したデータをオフラインに貯める
					store_data(data);
				}
			}else{
				//エラーメッセージをHTMLで表示
				status_area.innerHTML = 'Failed. HttpStatus: ' + xhr.statusText;
				//登録失敗したデータをオフラインに貯める
				store_data(data);
			}
			//現在の各種件数をHTMLに表示
			showCount();
			break;
		}
	}
}

//データをオフラインに貯める
function store_data(value){
	//ローカルストレージのデータを取り出し
	var data = localStorage.getItem("sensor");
	//JSON文字列をJSONデータに変換
	save_data = JSON.parse(data);
	//JSONデータの配列末尾に引数のオブジェクトを追加
	save_data.push(value);
	//JSONデータをJSON文字列に変換
	data = JSON.stringify(save_data);
	//ローカルストレージに保存
	localStorage.setItem("sensor", data);
	//現在の各種件数をHTMLに表示
	showCount();
}

//貯めたデータをまとめて送る
function send_stored_data(){
	//ローカルストレージのデータを取り出し
	var data = localStorage.getItem("sensor");
	//JSON文字列をJSONデータに変換
	save_data = JSON.parse(data);
	while(save_data.length > 0){
		//配列の先頭を送信
		send_data(save_data[0]);
		//配列の先頭を削除
		save_data.shift();
	}
	//JSONデータをJSON文字列に変換
	data = JSON.stringify(save_data);
	//ローカルストレージに保存
	localStorage.setItem("sensor", data);
}

//データの送信を開始する
function send_start(){
	send_flag = true;//フラグをオン
}

//データの送信を停止する
function send_stop(){
	send_flag = false;//フラグをオフ
}

//蓄積しているデータを全消去する
function remove_data(){
	//ローカルストレージを空配列で上書き
	localStorage.setItem("sensor", '[]');
}

//現在時刻を取得
function getTime(){
	//オブジェクト生成
	var d = new Date();
	//日時を取得
	var year  = d.getFullYear();        //年
	var month = niketa(d.getMonth()+1); //月（"0月"始まり->+1）
	var date  = niketa(d.getDate());    //日
	var hours = niketa(d.getHours());   //時
	var min   = niketa(d.getMinutes()); //分
	var sec   = niketa(d.getSeconds()); //秒
	//取得した日時を結合
	return year+'-'+month+'-'+date+' '+hours+':'+min+':'+sec; 
	
	//1桁のものは2桁に
	function niketa(value){
		return ("0"+value).slice(-2);
	}
}

//件数をHTMLに表示
function showCount(){
	//送信前の件数を表示
	var w = document.getElementById('waitnum');
	w.innerHTML = wait_data.length + '件登録中';
	//送信失敗で蓄積された件数を表示
	var s = document.getElementById('savenum');
	s.innerHTML = save_data.length + '件登録中';
}

//JSONデータをPOSTメソッドで送れる文字列に変換
function encodeURLParm(data){
    var params = [];
    for(var name in data){
        var value = data[name];
        var param = encodeURIComponent(name) + '=' + encodeURIComponent(value);
        params.push(param);
    }
    return params.join('&').replace(/%20/g, '+');
}

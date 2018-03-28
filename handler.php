<?php
	if (!isset($_REQUEST)) { 
		return; 
	}
	
	$confirmationToken = 'yourConfiramtionToken';
	$token = '12345678replaceMeWithRealToken';
	$secretKey = 'yourSecretKey(you can dont use it if you want)';
	$adminId = array(/*your vk account id or ids*/);
	
	function request($url) { 
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_URL, $url);
		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}
	
	function logMess($message) {
		$fp = fopen("tfrlog.txt","a");
		fwrite($fp, '
' . '[' . date('d m Y H:i:s') . '] ' .$message);
		fclose($fp);
	}
	
	function registerMessage($msgId) {
		logMess('tfrmessageIds.txt contains ' . file_get_contents("messageIds.txt") . ' message id : ' . $msgId);
		$arr = json_decode(file_get_contents("tfrmessageIds.txt"));
		$ids = $arr->elements;
		for ($i = 0; $i < 5; $i++) {
			if ($ids[$i] == $msgId) {
				return false;
			}
		}

		for ($i = 0; $i < 4; $i++) {
			$ids[$i] = $ids[$i + 1];
		}
		$ids[4] = $msgId;
		$toWrite = "{ \"elements\" : [ {$ids[0]}, {$ids[1]}, {$ids[2]}, {$ids[3]}, {$ids[4]} ] }";
		logMess("writing {$toWrite} to messageIds.txt file");
		$writing = fopen("tfrmessageIds.txt", "w");
		fwrite($writing, $toWrite);
		fclose($writing);
		return true;
	}
	
	function addPic($picLink, $country, $parameter) {
		$pics = json_decode(file_get_contents("proofsdata.txt"), true);
		if(isset($pics[$country]) == false) {
			$pics[$country] = array($parameter => $picLink);
		} else {
			if(isset($pics[$country][$parameter]) !== false) {
				$pics[$country][$parameter] .= ',' . $picLink;
			} else {
				$pics[$country][$parameter] = $picLink;
			}
		}
		$file = fopen('proofsdata.txt', 'w');
		fwrite($file, json_encode($pics));
		fclose($file);
	}
	
	$data = json_decode(file_get_contents('php://input')); 
	$inp = file_get_contents('php://input');
	logMess("request: {$inp}");
	if(strcmp($data->secret, $secretKey) !== 0 && strcmp($data->type, 'confirmation') !== 0) {
		logMess('returned without continuing');
		return;
	}		
	
	switch ($data->type) {
		case 'confirmation': 
			echo $confirmationToken; 
			logMess('response: ' . $confirmationToken);
			break;
		case 'message_new':
			if (registerMessage($data->object->id)) {
				$userId = $data->object->user_id;
				$body = $data->object->body;
				$userInfo = json_decode(request("https://api.vk.com/method/users.get?user_ids={$userId}&v=5.0"));
				$user_name = $userInfo->response[0]->first_name;
				$body_parsed = explode(' ', $body);
				logMess('body parsed' . $body_parsed[0]);
				if ((strripos(mb_strtolower($body), 'что такое советская власть?') !== false) or (strripos(mb_strtolower($body), 'что такое советская власть') !== false)){
					$request_params = array('attachment' => "video174028553_171889530", 'message' => 'Советская власть это...', 'user_id' => $userId, 'access_token' => $token, 'v' => '5.0' );
				} elseif ((strcmp(mb_strtolower($body_parsed[0]), "/proofs") == 0) or (strcmp(mb_strtolower($body_parsed[0]), "/пруфы") == 0) or (strcmp(mb_strtolower($body_parsed[0]), "/пруф") == 0)) {
					logMess('proofs cmd detected');
					$pic = json_decode(file_get_contents("proofsdata.txt"), true)[$body_parsed[1]][$body_parsed[2]];
					$request_params = array('attachment' => $pic, 'message' => 'Вот, держи', 'user_id' => $userId, 'access_token' => $token, 'v' => '5.0' );
					if(isset($pic) == false) {
						$request_params = array('attachment' => "photo165054978_456241750_38630acee6004df4e3", 'user_id' => $userId, 'access_token' => $token, 'v' => '5.0' );
					}
					if((strcmp(mb_strtolower($body_parsed[1]), 'add') == 0) or (strcmp(mb_strtolower($body_parsed[1]), 'добавить') == 0)) {
						logMess('proofs add cmd detected');
						$photo = 'photo' . $data->object->attachments[0]->photo->owner_id . '_' . $data->object->attachments[0]->photo->id;
						if(isset($data->object->attachments[0]->photo->access_key) !== false) {
							$photo .= '_' . $data->object->attachments[0]->photo->access_key;
						} 
						$request_params = array('forward_messages' => '' . $data->object->id, 'message' => 'Ожидает модерации фото со ссылкой: ' . $photo, 'user_id' => $adminId, 'access_token' => $token, 'v' => '5.0' );
						if(in_array($data->object->user_id, $adminId)) {
							$admins = "";
							for($i = 0; $i < count($adminId); $i++) {
							 $admins .= $adminId[$i];
							 if(!$i+1 == count($adminId)) {
							  $admins .= ",";
							 }
							}
							addPic($photo, $body_parsed[2], $body_parsed[3]);
							$request_params = array('forward_messages' => '' . $data->object->id, 'message' => 'Одобрено: ' . $photo, 'user_ids' => '' . $admins . ',' . $body_parsed[4], 'access_token' => $token, 'v' => '5.0' );
						}
						
					} elseif ((strcmp($body_parsed[1], "list") == 0) or (strcmp($body_parsed[1], "список") == 0)) {
						$json_arr = json_decode(file_get_contents("proofsdata.txt"), true);
						$keys = array_keys($json_arr);
						$message = "\n";
						for($i = 0; $i < count($keys); $i++) {
							$keys_in = array_keys($json_arr[$keys[$i]]);
							$message .=  "" . $i+1 . ") " . $keys[$i] . ": \n" ;
							for($j = 0; $j < count($json_arr[$keys[$i]]); $j++) {
								$message .= "" . $j+1 . ". " . $keys_in[$j] . "; \n";
							}
						}
						$request_params = array('message' => "Доступно на данный момент: " . $message , 'user_id' => $userId, 'access_token' => $token, 'v' => '5.0' );
					}
				} elseif ((strcmp(mb_strtolower($body_parsed[0]), "/help") == 0) or (strcmp(mb_strtolower($body_parsed[0]), "помощь") == 0) or (strcmp(mb_strtolower($body_parsed[0]), "команды") == 0)) {
					$request_params = array('message' => "На данный момент доступны команды:
/proofs <country> <parameter> (Вместо \"/proofs\" можно писать \"/пруф\" или \"/пруфы\". Выводит график или таблицу, которую Вы запросили. Название страны и параметра пишутся на английском и без знаков больше/меньше.)
/proofs add [<примечание для модератора>] (К команде прикрепите изображение, которое хотите добавить в бота и ожидайте одобрения модератора. В примечании укажите, что Вы прикрепили к команде, например \"Количество осужденных к высшей мере в СССР\")
/proofs list или /proofs список (Выводит спиок всех доступных стран и параметров).
/help (Помощь, которую Вы сейчас читаете)" . $message , 'user_id' => $userId, 'access_token' => $token, 'v' => '5.0' );
				}
				$get_params = http_build_query($request_params);
				request('https://api.vk.com/method/messages.send?' . $get_params);
				logMess('registerMessage($data->object->id) == true, passed');
			} else {
				logMess('registerMessage($data->object->id) == false, not passed');
			}
			echo 'ok';
			logMess('response: \'ok\'');
			break;
	}
?>

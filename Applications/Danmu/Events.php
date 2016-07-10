<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */
use \GatewayWorker\Lib\Gateway;

class Events {
	/**
	 * 获取组的配置数组
	 * @param int $group_id
	 */
	protected static function getGroupConfigById($group_id){
		if(!isset($_SERVER['GROUP_CONFIG'])) {
			$_SERVER['GROUP_CONFIG'] = array();
		}
		$group_config = $_SERVER['GROUP_CONFIG'];
		if(!isset($group_config[$group_id])) {
			$groupconfig = array();
			
			// 不存在 或 30分钟之后 更新配置
			if(!isset($groupconfig['updatetime']) || (time() - $groupconfig['updatetime']) > 30*60) {
				$roomconfig = file_get_contents(sprintf(Settings::CONFIG_GET_URL, $group_id));
				$roomconfig = json_decode($roomconfig);
				$groupconfig['updatetime'] = time();
				$groupconfig['upuid'] = $roomconfig->data->upuid;
				$groupconfig['danmu'] = $roomconfig->data->danmu;
				$groupconfig['isopen'] = $roomconfig->data->isopen;
			}
			$group_config[$group_id] = $groupconfig;
		}
		return $group_config[$group_id];
	}

	/**
	 * 有消息时
	 * @param int $client_id
	 * @param mixed $message
	 */
	public static function onMessage($client_id, $message) {
		// debug
		echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";

		// 客户端传递的是json数据
		$message_data = json_decode($message, true);
		if (!$message_data) {
			return;
		}

		// 根据类型执行不同的业务
		switch ($message_data['type']) {
			// 客户端回应服务端的心跳，回应房间人数
			case 'pong':
				// 非法请求
				if (!isset($_SESSION['room_id'])) {
					throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
				}
				$room_id = $_SESSION['room_id'];

				// 回应心跳和当前房间人数 message格式： { type: 'pong', num: xxx }
				$new_message = array('type' => 'pong', 'num' => Gateway::getClientCountByGroup($room_id));

				return Gateway::sendToCurrentClient(json_encode($new_message));
			// 客户端登录 message格式: {type:login, uid:xx, token:xx, room_id:1} ，认证用户信息，添加到客户端
			case 'login':
				// 判断是否有房间号
				if (!isset($message_data['room_id'])) {
					throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
				}

				// 把房间号昵称放到session中
				$room_id = $message_data['room_id'];
				// 认证逻辑 认证失败作为游客
				$client_uid = $message_data['uid'];
				$client_token = $message_data['token'];
				$client_name = '游客';
				$client_auth = false;
				$client_isup = false; // 当前房间所有者
				
				// 未配置认证则默认为 游客
				if(Settings::AUTH_URL) {
					$authjson = file_get_contents(sprintf(Settings::AUTH_URL, $client_token, $client_uid));
					$auth = json_decode($authjson);
					if($auth->success) {
						$client_name = $auth->data->username;
						$client_auth = true; // 是否认证，认证失败无发送消息权限
					}
				}

				// 配置获取配置地址 检查房间配置
				if (Settings::CONFIG_GET_URL) {
					$roomconfig = self::getGroupConfigById($room_id);
					echo json_encode($roomconfig);
					// 房间uid与客户端uid匹配 并且 客户端已认证
					if(($roomconfig['upuid']==$client_uid) && $client_auth){
						$client_isup = true; // 成为房间所有者
					}
				}
				
				$_SESSION['room_id'] = $room_id;
				$_SESSION['client_name'] = $client_name;
				$_SESSION['client_uid'] = $client_uid;	
				$_SESSION['client_auth'] = $client_auth;
				$_SESSION['client_isup'] = false;

				// 回应登录信息 message格式 {type:login, state:true/false, data:[client_id,client_name], num:xxx}
				$new_message = array('type'=> 'login', 'state'=> true, 'data'=>array($client_id, $client_name), 'num' => Gateway::getClientCountByGroup($room_id));
				
				Gateway::joinGroup($client_id, $room_id);
				Gateway::sendToCurrentClient(json_encode($new_message));

				// 广播当前房间人数 {type:'sys_num', num:123 }
				$new_message = array('type' => 'sys_num', 'num' => Gateway::getClientCountByGroup($room_id));
				return Gateway::sendToGroup($room_id, json_encode($new_message));

			// 客户端弹幕 message: {type:danmu, content:xx, time:xx}
			case 'danmu':
				// 非法请求
				if (!isset($_SESSION['room_id'])) {
					throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
				}

				// 游客发送的弹幕不进行广播
				if(!$_SESSION['client_auth']) {
					return;
				}

				$room_id = $_SESSION['room_id'];
				$client_name = $_SESSION['client_name'];
				$client_isup = $_SESSION['client_isup'];
				
				// 广播弹幕格式 {type:'danmu', data:[[client_id,client_name,...], 'danmudata', 'time']}
				$new_message = array(
					'type' => 'danmu',
					'data' => array(
						array(
							$client_id,
							$client_name,
							$client_isup
						),
						nl2br(htmlspecialchars($message_data['content'])),
						date('Y-m-d H:i:s')
					)
				);
				return Gateway::sendToGroup($room_id, json_encode($new_message));
			case 'sys_config':
				// 非法请求和未认证无法使用此命令
				if (!isset($_SESSION['room_id']) && !$_SESSION['client_auth']) {
					return;
				}

				// 未设置配置获取地址无法使用此命令
				if (!Settings::CONFIG_GET_URL) {
					return;
				}

				$groupconfig = self::getGroupConfigById($_SESSION['room_id']);

				// 返回房间状态 {type:'sys_config', data: { 'up':'xxx', 'danmu':true, 'open':false, 'roomid':123 }}
				$new_message = array('type' => 'sys_config', 'data' => array( 'up' => $groupconfig['upuid'], 'danmu' => $groupconfig['danmu'], 'open' => $groupconfig['isopen'], 'roomid' => $_SESSION['room_id']));
				return Gateway::sendToCurrentClient(json_encode($new_message));
		}
	}

	/**
	 * 当客户端断开连接时
	 * @param integer $client_id 客户端id
	 */
	public static function onClose($client_id) {
		// debug
		echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";

	}

}

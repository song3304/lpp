<?php
namespace Plugins\Wechat\App\Http\Controllers;

use Illuminate\Http\Request;

//use Addons\Core\Controllers\Controller;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Database\Eloquent\Collection;

use Plugins\Wechat\App\Tools\API;
use Plugins\Wechat\App\Tools\Account as WechatAccountTool;
use Plugins\Wechat\App\Tools\User as WechatUserTool;
use Plugins\Wechat\App\Tools\Pay as WechatPayTool;
use Plugins\Wechat\App\WechatDepotNews;
use Plugins\Wechat\App\WechatAccount;
use Plugins\Wechat\App\WechatUser;
use Plugins\Wechat\App\WechatReply;
use Plugins\Wechat\App\WechatMenu;
use Plugins\Wechat\App\WechatMessage;
use Plugins\Wechat\App\WechatMessageText;
use Plugins\Wechat\App\WechatMessageMedia;
use Plugins\Wechat\App\WechatMessageLink;
use Plugins\Wechat\App\WechatMessageLocation;
use Plugins\Wechat\App\WechatQrcode;
use Plugins\Wechat\App\WechatLog;
use Plugins\Wechat\App\WechatBill;
use Plugins\Wechat\App\Tools\Send;
abstract class WechatController extends Controller {
	//use DispatchesJobs;

	public function index(Request $request, $url, $wuid)
	{
		if (!empty($wuid))
		{
			$wechatUser = WechatUser::findOrFail($wuid);
			$account = $wechatUser->account;

			$api = new API($account->toArray(), $account->getKey());
			$this->auth($api, $wechatUser);
		}

		return redirect($url);
	}
	/**
	 * 微信推送接口，自动添加用户
	 * 
	 * @return string|response
	 */
	public function push(Request $request, $id = 0)
	{
		$api = $account = null;
		$_config = [/*'debug' => true, 'logcallback' => function(API $api, $log, $url = NULL){
			WechatLog::create(['log' => $log, 'waid' => $api->waid, 'url' => app('url')->full()]);
		}*/];
		if (empty($id)) //没有id，则尝试去数据库找
		{
			$api = new API(NULL, 0);
			if ($api->valid(true, false) && $to = @$api->getRev()->getRevTo())
			{
				$account = WechatAccount::where('account', $to)->firstOrFail();
				$api->setConfig($account->toArray() + $_config, $account->getKey());
			}
			else
				return null;
		} else {
			$account = WechatAccount::findOrFail($id);
			$api = new API($account->toArray() + $_config, $account->getKey());
		}
		
		$wechatUserTool = new WechatUserTool($api);

		$api->valid();
		$rev = $api->getRev();
		$type = $rev->getRevType();
		$from = $rev->getRevFrom();
		$to = $rev->getRevTo();

		$wechatUser = $wechatUserTool->updateWechatUser($from);
		$user = $this->user($api, $wechatUser);
		empty($wechatUser->uid) && !empty($user) && $wechatUser->uid = $user->getKey();

		!in_array($type, [API::MSGTYPE_EVENT]) && $message = WechatMessage::create(['waid' => $api->waid, 'wuid' => $wechatUser->getKey(), 'message_id' => $rev->getRevID(), 'type' => $type, 'transport_type' => 'receive']);

		switch($type) {
			case API::MSGTYPE_TEXT: //文字消息
				$text = WechatMessageText::create(['id' => $message->getKey(), 'content' => $rev->getRevContent()]);
				return $this->text($api, $wechatUser, $message, $text);
			case API::MSGTYPE_IMAGE: //图片消息
				$data = $rev->getRevPic();
				$image = WechatMessageMedia::create(['id' => $message->getKey(), 'media_id' => $data['mediaid'], 'format' => 'jpg']); //auto download
				return $this->image($api, $wechatUser, $message, $image);
			case API::MSGTYPE_VOICE: //音频消息
				$data = $rev->getRevVoice();
				$voice = WechatMessageMedia::create(['id' => $message->getKey(), 'media_id' => $data['mediaid'], 'format' => $data['format']]); //auto download
				return $this->voice($api, $wechatUser, $message, $voice);
			case API::MSGTYPE_VIDEO: //视频消息
				$data = $rev->getRevVideo();
				$video = WechatMessageMedia::create(['id' => $message->getKey(), 'media_id' => $data['mediaid'], 'thumb_media_id' => $data['thumbmediaid'], 'format' => 'mp4']); //auto download
				return $this->video($api, $wechatUser, $message, $video);
			case API::MSGTYPE_SHORTVIDEO: //小视频消息
				$data = $rev->getRevVideo();
				$shortvideo = WechatMessageMedia::create(['id' => $message->getKey(), 'media_id' => $data['mediaid'], 'thumb_media_id' => $data['thumbmediaid'], 'format' => 'mp4']); //auto download
				return $this->shortvideo($api, $wechatUser, $message, $shortvideo);
			case API::MSGTYPE_LOCATION: //地址消息
				$data = $rev->getRevGeo();
				$location = WechatMessageLocation::create(['id' => $message->getKey(), 'x' => $data['x'], 'y' => $data['y'], 'scale' => $data['scale'], 'label' => $data['label']]);
				return $this->location($api, $wechatUser, $message, $location);
			case API::MSGTYPE_LINK: //链接消息
				$data = $rev->getRevLink();
				$link = WechatMessageLink::create(['id' => $message->getKey(), 'title' => $data['title'], 'description' => $data['description'], 'url' => $data['url']]);
				return $this->link($api, $wechatUser, $message, $link);
			case API::MSGTYPE_EVENT: //事件
				$event = $rev->getRevEvent();
				switch ($event['event']) { 
					case 'subscribe':
						if (empty($event['key']))//关注微信
							return $this->subscribe($api, $wechatUser, $account);
						else //扫描关注
							return $this->scan_subscribe($api, $wechatUser, $account, $rev->getRevSceneId(), $rev->getRevTicket());
					case 'unsubscribe': //取消关注
						return $this->unsubscribe($api, $wechatUser, $account);
					case 'SCAN': //扫描二维码
						return $this->scan($api, $wechatUser, $account, $event['key'], $rev->getRevTicket());
					case 'LOCATION': //地址推送
						return $this->location_event($api, $wechatUser, $account, $rev->getRevEventGeo());
					case 'CLICK': //点击触发事件
						return $this->click($api, $wechatUser, $account, $event['key']);
					case 'VIEW': //浏览器打开
						return $this->browser($api, $wechatUser, $account, $event['key']);
					case 'scancode_push': //扫码推事件的事件推送
						return $this->scancode_push($api, $wechatUser, $account, $event['key'], $rev->getRevScanInfo());
					case 'scancode_waitmsg': //扫码推事件且弹出“消息接收中”提示框的事件推送
						return $this->scancode_waitmsg($api, $wechatUser, $account, $event['key'], $rev->getRevScanInfo());
					case 'pic_sysphoto': //弹出系统拍照发图的事件推送
						return $this->pic_sysphoto($api, $wechatUser, $account, $event['key'], $rev->getRevSendPicsInfo());
					case 'pic_photo_or_album': //弹出拍照或者相册发图的事件推送
						return $this->pic_photo_or_album($api, $wechatUser, $account, $event['key'], $rev->getRevSendPicsInfo());
					case 'pic_weixin': //弹出微信相册发图器的事件推送
						return $this->pic_weixin($api, $wechatUser, $account, $event['key'], $rev->getRevSendPicsInfo());
					case 'location_select': //弹出微信地址选择的事件推送
						return $this->location_select($api, $wechatUser, $account, $event['key'], $rev->getRevSendGeoInfo());
					
				}
				break;
		}
	}

	abstract protected function auth(API $api, WechatUser $wechatUser);
	abstract protected function user(API $api, WechatUser $wechatUser);

	public function choose(Request $request, $url = NULL)
	{
		$accounts = WechatAccount::all();

		return view('wechat::wechat.choose')->with('_accounts', $accounts)->with('_account', WechatAccount::find((new WechatAccountTool)->getAccountID()))->with('_url', $url);
	}

	public function chooseQuery(Request $request, $id, $url)
	{
		$account = WechatAccount::findOrFail($id);
		(new WechatAccountTool)->setAccountID($account->getKey());

		return redirect()->intended($url);
	}

	public function modifingAccount(Request $request, WechatAccountTool $account)
	{
		$data = WechatAccount::find($account->getAccountID());
		$data = array_diff_key($data->toArray(), array_flip(['appsecret', 'token', 'encodingaeskey', 'mchkey']));
		return (new \Addons\Core\Controllers\Controller(FALSE))->success('', FALSE, $data);
	}

	public function news(Request $request, $id)
	{
		return null;
	}
	/**
	 * 支付回调
	 * @return [type] [description]
	 */
	public function feedback(Request $request, $aid, $oid = NULL)
	{
		$aid = $request->input('aid') ?: $aid;
		$oid = $request->input('oid') ?: $oid;
		$account = WechatAccount::findOrFail($aid);
		$api = new API($account->toArray(), $account->getKey());

		$pay = new WechatPayTool($api);
		$result = $pay->notify(function($result, &$message) use ($account){
			if ($result['return_code'] == 'SUCCESS')
			{
				$wechatUser = WechatUser::where('openid', $result['openid'])->firstOrFail();
				$result = array_only($result, ['return_code','return_msg','mch_id','device_info','result_code','err_code','err_code_des','trade_type','bank_type','total_fee','fee_type','cash_fee','cash_fee_type','coupon_fee','coupon_count','transaction_id','out_trade_no','attach','time_end']);
				WechatBill::create($result + ['waid' => $account->getKey(), 'wuid' => $wechatUser->getKey()]);
			} else
				WechatBill::create(['return_code' => $result['return_code'], 'return_msg' => $result['return_msg'], 'waid' => $account->getKey()]);
			return true;
		});
		return $result;
	}

	/**
	 * 文字消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageText $text 文本  
	 * @return string|response
	 */
	protected function text(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageText $text)
	{
		$depots = (new WechatReply)->autoReply($message);
		return $this->sendToUser($api, $wechatUser, $depots);
	}

	/**
	 * 图片消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageMedia $images  图片
	 * @return string|response
	 */
	protected function image(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageMedia $image)
	{
		return null;
	}

	/**
	 * 音频消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageMedia $voice 音频  
	 * @return string|response
	 */
	protected function voice(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageMedia $voice)
	{
		return null;
	}

	/**
	 * 视频消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageMedia $video 视频  
	 * @return string|response
	 */
	protected function video(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageMedia $video)
	{
		return null;
	}

	/**
	 * 小视频消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageMedia $video 视频  
	 * @return string|response
	 */
	protected function shortvideo(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageMedia $shortvideo)
	{
		return null;
	}

	/**
	 * 地址消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageLocation $location  地址
	 * @return string|response
	 */
	protected function location(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageLocation $location)
	{
		return null;
	}

	/**
	 * 链接消息
	 * 
	 * @param  Plugins\Wechat\App\Tools\API $api  微信API
	 * @param  Plugins\Wechat\App\WechatMessage $message  消息
	 * @param  Plugins\Wechat\App\WechatMessageLink $link  链接
	 * @return string|response
	 */
	protected function link(API $api, WechatUser $wechatUser, WechatMessage $message, WechatMessageLink $link)
	{
		return null;
	}

	/**
	 * 关注
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @return string|response
	 */
	protected function subscribe(API $api, WechatUser $wechatUser, WechatAccount $account)
	{
		$depots = (new WechatReply)->subscribeReply($account);
		return $this->sendToUser($api, $wechatUser, $depots);
	}

	/**
	 * 取消关注
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @return string|response
	 */
	protected function unsubscribe(API $api, WechatUser $wechatUser, WechatAccount $account)
	{
		return null;
	}

	/**
	 * 扫描关注
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $scene_id 二维码的参数值
	 * @param  string $ticket   二维码的ticket，可用来换取二维码图片
	 * @return string|response
	 */
	protected function scan_subscribe(API $api, WechatUser $wechatUser, WechatAccount $account, $scene_id, $ticket)
	{
		$depots = (new WechatQrcode)->subscribeReply($account, $scene_id, $ticket) ?: (new WechatReply)->subscribeReply($account);
		return $this->sendToUser($api, $wechatUser, $depots);
	}

	/**
	 * 扫描
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $scene_id 二维码的参数值
	 * @param  string $ticket   二维码的ticket，可用来换取二维码图片
	 * @return string|response
	 */
	protected function scan(API $api, WechatUser $wechatUser, WechatAccount $account, $scene_id, $ticket)
	{
		$depots = (new WechatQrcode)->reply($scene_id, $ticket);
		return $this->sendToUser($api, $wechatUser, $depots);
	}

	/**
	 * 上报地理位置事件
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  array $data     地理信息 ['x' => '', 'y' => '', 'precision' => '']
	 * @return string|response
	 */
	protected function location_event(API $api, WechatUser $wechatUser, WechatAccount $account, $data)
	{
		return null;
	}

	/**
	 * 自定义菜单事件
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     与自定义菜单接口中KEY值对应
	 * @return string|response
	 */
	protected function click(API $api, WechatUser $wechatUser, WechatAccount $account, $key)
	{
		if (preg_match('/key-(\d*)/i', $key, $matches)){
			$menu = WechatMenu::find($matches[1]);
			if (!empty($menu))
			{
				$menu->depot; //read it first
				$depots = !empty($menu->depot) ? new Collection([$menu->depot]) : false;
				return $this->sendToUser($api, $wechatUser, $depots);
			}
		}
		return null;
	}

	/**
	 * 点击菜单跳转链接时的事件推送，由于父Controller有view函数，此处改为browser
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $url     设置的跳转URL
	 * @return string|response
	 */
	protected function browser(API $api, WechatUser $wechatUser, WechatAccount $account, $url)
	{
		return null;
	}

	/**
	 * 扫码推事件的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $scan_info 扫描信息 [ 'ScanType'=>'qrcode', 'ScanResult'=>'']
	 * @return string|response
	 */
	protected function scancode_push(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $scan_info)
	{
		return null;
	}

	/**
	 * 扫码推事件且弹出“消息接收中”提示框的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $scan_info 扫描信息 [ 'ScanType'=>'qrcode', 'ScanResult'=>'']
	 * @return string|response
	 */
	protected function scancode_waitmsg(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $scan_info)
	{
		return null;
	}

	/**
	 * 弹出系统拍照发图的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $send_pics_info 发送的图片信息 ['Count' => '2', 'PicList' =>['item' => [ ['PicMd5Sum' => 'aaae42617cf2a14342d96005af53624c'], ['PicMd5Sum' => '149bd39e296860a2adc2f1bb81616ff8'] ] ] ]
	 * @return string|response
	 */
	protected function pic_sysphoto(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $send_pics_info)
	{
		return null;
	}

	/**
	 * 弹出拍照或者相册发图的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $send_pics_info 发送的图片信息 ['Count' => '2', 'PicList' =>['item' => [ ['PicMd5Sum' => 'aaae42617cf2a14342d96005af53624c'], ['PicMd5Sum' => '149bd39e296860a2adc2f1bb81616ff8'] ] ] ]
	 * @return string|response
	 */
	protected function pic_photo_or_album(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $send_pics_info)
	{
		return null;
	}

	/**
	 * 弹出微信相册发图器的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $send_pics_info 发送的图片信息 ['Count' => '2', 'PicList' =>['item' => [ ['PicMd5Sum' => 'aaae42617cf2a14342d96005af53624c'], ['PicMd5Sum' => '149bd39e296860a2adc2f1bb81616ff8'] ] ] ]
	 * @return string|response
	 */
	protected function pic_weixin(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $send_pics_info)
	{
		return null;
	}

	/**
	 * 弹出地理位置选择器的事件推送
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者
	 * @param  Plugins\Wechat\App\WechatAccount $account 接收者
	 * @param  string $key     由开发者在创建菜单时设定
	 * @param  array $send_geo_info 发送的位置信息 ['Location_X' => '', 'Location_Y' => '', 'Scale' => '', 'Label' => '', 'Poiname' => '']
	 * @return string|response
	 */
	protected function location_select(API $api, WechatUser $wechatUser, WechatAccount $account, $key, $send_geo_info)
	{
		return null;
	}

	/**
	 * 回復消息，此處一律採用自定義發送接口發送，而不是當時回復
	 * 
	 * @param  Plugins\Wechat\App\WechatUser $wechatUser  发送者 
	 * @param  Plugins\Wechat\App\WechatDepot    $depots  素材合集
	 * @param  boolean     $random     隨機發送幾條，默認全發
	 * @return 
	 */
	protected function sendToUser(API $api, WechatUser $wechatUser, Collection $depots, $random = NULL)
	{
		if (empty($depots) || $depots->isEmpty()) return NULL;

		$send = new Send($wechatUser, $api);
		foreach($depots as $depot)
			$send->add($depot);
		return $send->send($random, TRUE); //可以实时反馈
	}

}
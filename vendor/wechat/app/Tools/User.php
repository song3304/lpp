<?php
namespace Plugins\Wechat\App\Tools;

use Plugins\Wechat\App\Tools\API;
/*use App\User as UserModel;
use App\Role as RoleModel;*/
use Plugins\Attachment\App\Attachment as AttachmentModel;
use Plugins\Wechat\App\WechatUser;
use Cache;
use Carbon\Carbon;
class User {

	private $api;

	public function __construct($options, $waid = NULL)
	{
		$this->api = $options instanceof API ? $options : new API($options, $waid);
	}

	public function getAPI()
	{
		return $this->api;
	}
	/**
	 * 根据OPENID查询用户资料
	 * @param  string  $openid     OPENID
	 * @param  string  $access_token 如果是通过OAuth2授权，则需要传递此参数
	 * @param  boolean $cache        是否缓存该资料
	 * @return array                 返回对应资料
	 */
	public function getUserInfo($openid, $access_token = NULL, $cache = TRUE) {
		if (empty($openid))
			return FALSE;

		$result = array();
		$hashkey = 'wechat-userinfo-' . $openid. '/'.$this->api->appid;

		if (!$cache || is_null($result = Cache::get($hashkey, null))) {
			$result = empty($access_token) ? $this->api->getUserInfo($openid) : $this->api->getOauthUserinfo($access_token, $openid);
			if (!empty($access_token) && empty($result) && $this->api->errCode == '48001') //http://www.bubuko.com/infodetail-703997.html sope=snsapi_base时 未关注用户（重来没有关注或授权的微信用户）{"errcode":48001,"errmsg":"api unauthorized"}
				$result = $this->api->getUserInfo($openid);
			if (isset($result['nickname'])) { //订阅号 无法获取昵称，则不加入缓存
				$attachment = (new AttachmentModel)->download(0, $result['headimgurl'], 'wechat-avatar-'.$openid, 'jpg');
				$result['avatar_aid'] = $attachment->getKey();
				Cache::put($hashkey, $result, 12 * 60); //0.5 day
			}
		}
		return $result;
	}

	/**
	 * 更新微信资料(如果没有则添加用户资料)
	 * 
	 * @param  string $openid      	OPENID
	 * @param  string $access_token     如果是通过OAuth2授权，则需要传递此参数
	 * @param  string $role_name        组名，只在添加用户时有效
	 * @param  integer $update_expire 	多少分钟更新一次?
	 * @return integer                  返回UID
	 */
	public function updateWechatUser($openid, $access_token = NULL, $cache = TRUE)
	{
		if (empty($openid))
			return FALSE;
		$wechatUser = FALSE;
		$hashkey = 'update-wechatuser-'.$openid. '/'.$this->api->appid;
		if (!$cache || is_null($wechatUser = Cache::get($hashkey, null)))
		{
			$wechatUser = WechatUser::firstOrCreate([
				'openid' => $openid,
				'waid' => $this->api->waid,
			]);
			$wechat = $this->getUserInfo($wechatUser->openid, $access_token, $cache);
			/*
			无授权的OAuth2是无法获取资料的
			if (empty($wechat))
				throw new \Exception("Get wechat's user failure:" .$this->api->errCode .' '.$this->api->errMsg);*/
		
			//公众号绑定开放平台,可获取唯一ID
			if (empty($wechatUser->unionid) || !empty($wechat['unionid']))
				$wechatUser->update(['unionid' => isset($wechat['unionid']) ? $wechat['unionid'] : $wechatUser->openid.'/'.$this->api->appid]);
			if (isset($wechat['nickname']))
			{
				//将所有唯一ID匹配的资料都更新
				$wechatUsers = WechatUser::where('unionid', $wechatUser->unionid)->get();
				foreach($wechatUsers as $v)
					$v->update([
						'nickname' => $wechat['nickname'], 
						'gender' => $wechat['sex'],
						'is_subscribed' => !empty($wechat['subscribe']) , //没有打开开发者模式 无此字段
						'subscribed_at' => !empty($wechat['subscribe_time']) ? Carbon::createFromTimestamp($wechat['subscribe_time']) : NULL,
						'country' => $wechat['country'],
						'province' => $wechat['province'],
						'city' => $wechat['city'],
						'language' => $wechat['language'],
						'remark' => !empty($wechat['remark']) ? $wechat['remark'] : NULL,//没有打开开发者模式 无此字段
						'groupid' => !empty($wechat['groupid']) ? $wechat['groupid'] : NULL,//没有打开开发者模式 无此字段
						'avatar_aid' => $wechat['avatar_aid'],
					]);
				
			}
			$wechatUser = WechatUser::where('openid', $openid)->where('waid', $this->api->waid)->get()->first();
			Cache::put($hashkey, $wechatUser, config('cache.ttl'));
		}
		return $wechatUser;
	}

	public function bindToUser(WechatUser $wechatUser, $role_name = NULL, $cache = TRUE)
	{
		$userModel = config('auth.providers.users.model');
		$user = !empty($wechatUser->uid) ? $userModel::find($wechatUser->uid) : $userModel::findByName($wechatUser->unionid);
		empty($user) && $user = $userModel::add([
			'username' => $wechatUser->unionid,
			'password' => '',
		], $role_name);

		$wechatUser->update(['uid' => $user->getKey()]);

		$hashkey = 'update-user-from-wechat-'.$user->getKey();
		if (!$cache || is_null(Cache::get($hashkey, null)))
		{
			$user->update([
				'nickname' => $wechatUser->nickname,
				'gender' => $wechatUser->gender,
				'avatar_aid' => $wechatUser->avatar_aid,
			]);
			Cache::put($hashkey, time(), config('cache.ttl'));
		}
		return $user;
	}

}
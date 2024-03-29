<?php
namespace Plugins\Wechat\App\Tools;

use Plugins\Wechat\App\Tools\API;
use Cache,Session;
use Plugins\Wechat\App\WechatUser;
use Illuminate\Http\Exceptions\HttpResponseException;

class Address {

	private $api;
	private $wechatUser;
	private $accesstoken;

	public function __construct($options, $waid = NULL, WechatUser $wechatUser = NULL)
	{
		$this->api = $options instanceof API ? $options : new API($options, $waid);
		!empty($wechatUser) && $this->setWechatUser($wechatUser);
	}

	public function setWechatUser(WechatUser $wechatUser)
	{
		$this->wechatUser = $wechatUser;
		return $this;
	}

	public function authenticate()
	{	
		$result = $this->getAccessToken();
		if (!empty($result)) return TRUE;

		$url = app('url')->full();
		$json = $this->api->getOauthAccessToken();
		if (empty($json))
		{
			$oauth_url =$this->api->getOauthRedirect($url,"jsapi_address","snsapi_base");
			throw new HttpResponseException(redirect($oauth_url));//\Illuminate\Http\RedirectResponse
			return false;
		} else{
			$this->accesstoken = $json['access_token'];
			$this->setAccessToken([$json['access_token'], $_GET['code'], $_GET['state']], $json['expires_in']);
		}

		return TRUE;
	}

	public function getAccessToken()
	{
		//return Cache::get('wechat-oauth2-access_token-'.$this->wechatUser->getKey(), NULL);
	}

	private function setAccessToken($data, $expires)
	{
		//Cache::put('wechat-oauth2-access_token-'.$this->wechatUser->getKey(), $data, $expires / 60);
	}

	public function getAPI()
	{
		return $this->api;
	}

	/**
	 * 设置jsapi_address参数
	 */
	public function getConfig($url = NULL)
	{

		$timeStamp = time();
		$nonceStr = $this->api->generateNonceStr();
		list($access_token, $code, $state) = $this->getAccessToken();
		empty($url) && $url = app('url')->full();
		return [
			'addrSign' => $this->getAddrSign($url,$timeStamp,$nonceStr,$this->accesstoken),
			'signType' => 'sha1',
			'scope' => 'jsapi_address',
			'appId' => $this->api->appid,
			'timeStamp' => strval($timeStamp),
			'nonceStr' => $nonceStr,
		];
	}

	/**
	 * 获取收货地址JS的签名
	 */
	public function getAddrSign($url, $timeStamp, $nonceStr, $accesstoken = ''){
		$arrdata = array(
			'accesstoken' => $accesstoken,
			'appid' => $this->api->appid,
			'noncestr' => $nonceStr,
			'timestamp' => strval($timeStamp),
			'url' => $url,
		);
		return $this->api->getSignature($arrdata);
	}

}

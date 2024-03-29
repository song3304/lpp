<?php
namespace Plugins\Wechat\App;

use App\Model;
use Plugins\Wechat\App\WechatMessageTrait;
class WechatMessage extends Model{
	use WechatMessageTrait;

	protected $guarded = ['id'];

	public function account()
	{
		return $this->hasOne(get_namespace($this).'\\WechatAccount', 'id', 'waid');
	}

	public function user()
	{
		return $this->hasOne(get_namespace($this).'\\WechatUser', 'id', 'wuid');
	}

	public function relation()
	{
		$method = $this->type;
		return $this->$method();
	}

	public function depot()
	{
		return $this->hasOne(get_namespace($this).'\\WechatDepot', 'id', 'wdid');
	}

	public function link()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageLink', 'id', 'id');
	}

	public function location()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageLocation', 'id', 'id');
	}

	public function text()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageText', 'id', 'id');
	}

	public function media()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageMedia', 'id', 'id');
	}

	public function video()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageMedia', 'id', 'id');
	}

	public function shortvideo()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageMedia', 'id', 'id');
	}

	public function voice()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageMedia', 'id', 'id');
	}

	public function image()
	{
		return $this->hasOne(get_namespace($this).'\\WechatMessageMedia', 'id', 'id');
	}
}
<?php
namespace Plugins\Wechat\App;

use App\Model;
use Plugins\Wechat\App\WechatAccount;

class WechatQrcode extends Model{
	protected $guarded = ['id'];

	public function account()
	{
		return $this->hasOne(get_namespace($this).'\\WechatAccount', 'id', 'waid');
	}

	public function depot()
	{
		return $this->hasOne(get_namespace($this).'\\WechatDepot', 'id', 'wdid');
	}

	public function subscribe_depot()
	{
		return $this->hasOne(get_namespace($this).'\\WechatDepot', 'id', 'subscribe_wdid');
	}

	/**
	 * 扫描二维码关注自动回复
	 * 
	 * @return Illuminate\Support\Collection [Plugins\Wechat\App\WechatDepots, ...]
	 */
	public function subscribeReply(WechatAccount $account, $scene_id, $ticket)
	{
		$qr = $this->where('ticket', '=', $ticket)->where('waid', $account->getKey())->orderBy('updated_at','DESC')->first();
		return empty($qr) ? false : $qr->subscribe_depot()->get(); //返回数据集
	}

	/**
	 * 扫描二维码自动回复
	 * 
	 * @return Illuminate\Support\Collection [Plugins\Wechat\App\WechatDepots, ...]
	 */
	public function reply($scene_id, $ticket)
	{
		$qr = $this->where('ticket','=',$ticket)->orderBy('updated_at','DESC')->first();
		return empty($qr) ? false : $qr->depot()->get(); //返回数据集
 	}
}
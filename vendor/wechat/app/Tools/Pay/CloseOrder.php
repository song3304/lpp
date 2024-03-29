<?php
namespace Plugins\Wechat\App\Tools\Pay;

/**
 *
 * 关闭订单输入对象
 *
 */
class CloseOrder extends Base
{

	/**
	 * 设置微信分配的公众账号ID
	 * @param string $value
	 **/
	public function setAppid($value)
	{
		$this->values['appid'] = $value;
		return $this;
	}

	/**
	 * 获取微信分配的公众账号ID的值
	 * @return 值
	 **/
	public function getAppid()
	{
		return $this->values['appid'];
	}

	/**
	 * 判断微信分配的公众账号ID是否存在
	 * @return true 或 false
	 **/
	public function isAppidSet()
	{
		return array_key_exists('appid', $this->values);
	}

	/**
	 * 设置微信支付分配的商户号
	 * @param string $value
	 **/
	public function setMchId($value)
	{
		$this->values['mch_id'] = $value;
		return $this;
	}

	/**
	 * 获取微信支付分配的商户号的值
	 * @return 值
	 **/
	public function getMchId()
	{
		return $this->values['mch_id'];
	}

	/**
	 * 判断微信支付分配的商户号是否存在
	 * @return true 或 false
	 **/
	public function isMchIdSet()
	{
		return array_key_exists('mch_id', $this->values);
	}

	/**
	 * 设置子商户的商户号
	 * @param string $value
	 **/
	public function setSubMchId($value)
	{
		$this->values['sub_mch_id'] = $value;
		return $this;
	}

	/**
	 * 获取子商户号的值
	 * @return 值
	 **/
	public function getSubMchId()
	{
		return $this->values['sub_mch_id'];
	}

	/**
	 * 设置商户系统内部的订单号
	 * @param string $value
	 **/
	public function setOutTradeNo($value)
	{
		$this->values['out_trade_no'] = $value;
		return $this;
	}

	/**
	 * 获取商户系统内部的订单号的值
	 * @return 值
	 **/
	public function getOutTradeNo()
	{
		return $this->values['out_trade_no'];
	}

	/**
	 * 判断商户系统内部的订单号是否存在
	 * @return true 或 false
	 **/
	public function isOutTradeNoSet()
	{
		return array_key_exists('out_trade_no', $this->values);
	}

	/**
	 * 设置商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号
	 * @param string $value
	 **/
	public function setNonceStr($value)
	{
		$this->values['nonce_str'] = $value;
		return $this;
	}

	/**
	 * 获取商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号的值
	 * @return 值
	 **/
	public function getNonceStr()
	{
		return $this->values['nonce_str'];
	}

	/**
	 * 判断商户系统内部的订单号,32个字符内、可包含字母, 其他说明见商户订单号是否存在
	 * @return true 或 false
	 **/
	public function isNonceStrSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}
}
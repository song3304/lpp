<?php
namespace Plugins\Wechat\App\Tools\Pay;

/**
 *
 * 下载对账单输入对象
 *
 */
class DownloadBill extends Base
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
	 * 设置微信支付分配的终端设备号，填写此字段，只下载该设备号的对账单
	 * @param string $value
	 **/
	public function setDeviceInfo($value)
	{
		$this->values['device_info'] = $value;
		return $this;
	}

	/**
	 * 获取微信支付分配的终端设备号，填写此字段，只下载该设备号的对账单的值
	 * @return 值
	 **/
	public function getDeviceInfo()
	{
		return $this->values['device_info'];
	}

	/**
	 * 判断微信支付分配的终端设备号，填写此字段，只下载该设备号的对账单是否存在
	 * @return true 或 false
	 **/
	public function isDeviceInfoSet()
	{
		return array_key_exists('device_info', $this->values);
	}

	/**
	 * 设置随机字符串，不长于32位。推荐随机数生成算法
	 * @param string $value
	 **/
	public function setNonceStr($value)
	{
		$this->values['nonce_str'] = $value;
		return $this;
	}

	/**
	 * 获取随机字符串，不长于32位。推荐随机数生成算法的值
	 * @return 值
	 **/
	public function getNonceStr()
	{
		return $this->values['nonce_str'];
	}

	/**
	 * 判断随机字符串，不长于32位。推荐随机数生成算法是否存在
	 * @return true 或 false
	 **/
	public function isNonceStrSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}

	/**
	 * 设置下载对账单的日期，格式：20140603
	 * @param string $value
	 **/
	public function setBillDate($value)
	{
		$this->values['bill_date'] = $value;
		return $this;
	}

	/**
	 * 获取下载对账单的日期，格式：20140603的值
	 * @return 值
	 **/
	public function getBillDate()
	{
		return $this->values['bill_date'];
	}

	/**
	 * 判断下载对账单的日期，格式：20140603是否存在
	 * @return true 或 false
	 **/
	public function isBillDateSet()
	{
		return array_key_exists('bill_date', $this->values);
	}

	/**
	 * 设置ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单
	 * @param string $value
	 **/
	public function setBillType($value)
	{
		$this->values['bill_type'] = $value;
		return $this;
	}

	/**
	 * 获取ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单的值
	 * @return 值
	 **/
	public function getBillType()
	{
		return $this->values['bill_type'];
	}

	/**
	 * 判断ALL，返回当日所有订单信息，默认值SUCCESS，返回当日成功支付的订单REFUND，返回当日退款订单REVOKED，已撤销的订单是否存在
	 * @return true 或 false
	 **/
	public function isBillTypeSet()
	{
		return array_key_exists('bill_type', $this->values);
	}
}
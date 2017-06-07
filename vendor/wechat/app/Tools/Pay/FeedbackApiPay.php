<?php
namespace Plugins\Wechat\App\Tools\Pay;

/**
 *
 * 提交扫码支付一API输入对象
 *
 */
class FeedbackApiPay extends Base
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
	 * 设置返回状态码
	 * @param string $value
	 **/
	public function setReturnCode($value)
	{
		$this->values['return_code'] = $value;
		return $this;
	}

	/**
	 * 获取返回状态码
	 * @return 
	 **/
	public function getReturnCode()
	{
		return $this->values['return_code'];
	}

	/**
	 * 判断返回状态码是否存在
	 * @return true 或 false
	 **/
	public function isReturnCodeSet()
	{
		return array_key_exists('return_code', $this->values);
	}

	/**
	 * 随机字符串
	 * @param string $value
	 **/
	public function setNonceStr($value)
	{
		$this->values['nonce_str'] = $value;
		return $this;
	}

	/**
	 * 获取notify随机字符串值
	 * @return 值
	 **/
	public function getNonceStr()
	{
		return $this->values['nonce_str'];
	}

	/**
	 * 判断随机字符串是否存在
	 * @return true 或 false
	 **/
	public function isNonceStrSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}

	/**
	 * 设置返回信息
	 * @param string $value
	 **/
	public function setReturnMsg($value)
	{
		$this->values['return_msg'] = $value;
		return $this;
	}

	/**
	 * 获取返回信息
	 * @return 
	 **/
	public function getReturnMsg()
	{
		return $this->values['return_msg'];
	}

	/**
	 * 判断订单返回信息
	 * @return true 或 false
	 **/
	public function isReturnMsgSet()
	{
		return array_key_exists('return_msg', $this->values);
	}

	/**
	 * 设置商户号
	 * @param string $value
	 **/
	public function setMchId($value)
	{
		$this->values['mch_id'] = $value;
		return $this;
	}

	/**
	 * 获取商户号
	 * @return
	 **/
	public function getMchId()
	{
		return $this->values['mch_id'];
	}

	/**
	 * 判断商户号是否存在
	 * @return true 或 false
	 **/
	public function isMchIdSet()
	{
		return array_key_exists('mch_id', $this->values);
	}

	/**
	 * 设置预支付ID
	 * @param string $value
	 **/
	public function setPrepayId($value)
	{
		$this->values['prepay_id'] = $value;
		return $this;
	}

	/**
	 * 获取预支付ID
	 * @return 
	 **/
	public function getPrepayId()
	{
		return $this->values['prepay_id'];
	}

	/**
	 * 判断预支付ID是否存在
	 * @return true 或 false
	 **/
	public function isPrepayIdSet()
	{
		return array_key_exists('prepay_id', $this->values);
	}
	/**
	 * 设置业务结果
	 * @param string $value
	 **/
	public function setResultCode($value)
	{
	    $this->values['result_code'] = $value;
	    return $this;
	}
	
	/**
	 * 获取业务结果
	 * @return 值
	 **/
	public function getResultCode()
	{
	    return $this->values['result_code'];
	}
	
	/**
	 * 判断业务结果是否存在
	 * @return true 或 false
	 **/
	public function isResultCodeSet()
	{
	    return array_key_exists('result_code', $this->values);
	}
	/**
	 * 设置错误描述
	 * @param string $value
	 **/
	public function setErrCodeDes($value)
	{
	    $this->values['err_code_des'] = $value;
	    return $this;
	}
	
	/**
	 * 获取错误描述
	 * @return
	 **/
	public function getErrCodeDes()
	{
	    return $this->values['err_code_des'];
	}
	
	/**
	 * 判断错误描述是否存在
	 * @return true 或 false
	 **/
	public function isErrCodeDesSet()
	{
	    return array_key_exists('err_code_des', $this->values);
	}
}
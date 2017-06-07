<?php
namespace Plugins\Wechat\App\Tools;

use Plugins\Wechat\App\Tools\Pay\FeedbackApiPay;
use Plugins\Wechat\App\Tools\API;
use Exception;
class Feedback {
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
	 * 
	 * 获取jsapi支付的参数
	 * @param array $UnifiedOrderResult 统一支付接口返回的数据
	 * @throws Exception
	 * 
	 * @return json数据，可直接填入js函数作为参数
	 */
	public function getPayXML($UnifiedOrderResult)
	{
		if(!array_key_exists("appid", $UnifiedOrderResult)
		|| !array_key_exists("prepay_id", $UnifiedOrderResult)
		|| $UnifiedOrderResult['prepay_id'] == "")
		{
			throw new Exception("参数错误");
		}
		$feedback_api = new FeedbackApiPay();
		$feedback_api ->setReturnCode('SUCCESS')
		              ->setReturnMsg(!empty($UnifiedOrderResult['return_msg'])?$UnifiedOrderResult['return_msg']:'')
		              ->setAppid($UnifiedOrderResult["appid"])
		              ->setMchId($UnifiedOrderResult['mch_id'])
		              ->setNonceStr($UnifiedOrderResult['nonce_str'])
		              ->setPrepayId($UnifiedOrderResult['prepay_id'])
		              ->setResultCode($UnifiedOrderResult['result_code'])
		              ->setErrCodeDes(!empty($UnifiedOrderResult['err_code_des'])?$UnifiedOrderResult['err_code_des']:'');
		$feedback_api->setSign($this->api->mchkey);
		return $feedback_api->toXml();
	}
}
<?php
/**
 * 通联收银宝(平安银行支付类)
 * Authors : ZL
 * Date    : 2020-01-07 16:33:09
 */
namespace Org\Util;

class PabankPay
{

    private $appid;  //APPID
    private $cusid;  //CUSID 商户号
    private $appkey; //APPKEY

    public function __construct($appid, $cusid, $appkey)
    {
        $this->appid  = $appid;
        $this->cusid  = $cusid;
        $this->appkey = $appkey;
    }

    /**
     * 请求支付
     * 2020年01月07日16点40分
     * 返回二维码串
     */
    public function pabank_pay($payfor, $paytype, $out_trade_no, $_body)
    {

        header("Content-type:text/html;charset=utf-8");
        //组装参数
        if ($paytype == 1) {
            $pay_type = "W01";
            $_bodys   = $_body;
        } elseif ($paytype == 2) {
            $pay_type = "A01";
            $_bodys   = "";
        } elseif ($paytype == 3) {
            $pay_type = "U01";
            $_bodys   = "";
        }
        //生成商户订单号
        $now               = date("YmdHis", time());
        $params            = array();
        $params["cusid"]   = $this->cusid;
        $params["appid"]   = $this->appid;
        $params["version"] = 11;
        $params["body"]    = $_bodys;
        $params["remark"]  = "";
/*      $params["validtime"] = ;               //订单有效时间*/
        $params["trxamt"]     = $payfor * 100; //交易金额 单位为分
        $params["reqsn"]      = $out_trade_no; //订单号,自行生成
        $params["paytype"]    = $pay_type;     //交易方式 1 W01 微信扫码支付 2 A01 支付宝扫码支付 3 U01 银联扫码支付(CSB)
        $params["randomstr"]  = $now;          //随机字符串
        $params["notify_url"] = "https://" . $_SERVER['HTTP_HOST'] . "/Hkm/Pabankpay/notifyurlmessage/";
        $params["sign"]       = $this->SignArray($params, $this->appkey); //签名

        $paramsStr = $this->ToUrlParams($params);

        $url = "https://vsp.allinpay.com/apiweb/unitorder/pay";

        $rsp = $this->request($url, $paramsStr);

        $rspArray = json_decode($rsp, true);

        //验签是否合法
        if ($this->validSign($rspArray)) {
            return $rspArray['payinfo'];
        } else {
            return false;
        }

    }

    /**
     * 交易查询
     * 2020年06月29日14点05分
     * 定时任务
     */
    public function pabank_query($out_trade_no)
    {
        //组装请求参数
        $params            = array();
        $params["cusid"]   = $this->cusid;
        $params["appid"]   = $this->appid;
        $params["version"] = 11;
        $params["reqsn"]      = $out_trade_no; 
        $params["randomstr"]  = time();          
        $params["sign"]       = $this->SignArray($params, $this->appkey); //签名

        $paramsStr = $this->ToUrlParams($params);

        $url = "https://vsp.allinpay.com/apiweb/unitorder/query";

        $rsp = $this->request($url, $paramsStr);

        $rspArray = json_decode($rsp, true);

        //验签是否合法
        if ($this->validSign($rspArray)) {
            return $rspArray;
        } else {
            return false;
        }

    }

    //模拟请求
    private function request($url, $params)
    {
        $ch          = curl_init();
        $this_header = array("content-type: application/x-www-form-urlencoded;charset=UTF-8");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this_header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function SignArray(array $array, $appkey)
    {
        $array['key'] = $appkey; // 将key放到数组中一起进行排序和组装
        ksort($array);
        $blankStr = $this->ToUrlParams($array);
        $sign     = md5($blankStr);
        return $sign;
    }

    private function ToUrlParams(array $array)
    {
        $buff = "";
        foreach ($array as $k => $v) {
            if ($v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    //验签
    private function validSign($array)
    {
        if ("SUCCESS" == $array["retcode"]) {
            $signRsp       = strtolower($array["sign"]);
            $array["sign"] = "";
            $sign          = strtolower($this->SignArray($array, $this->appkey));
            if ($sign == $signRsp) {
                return true;
            } else {
                echo "验签失败:" . $signRsp . "--" . $sign;
            }
        } else {
            echo $array["retmsg"];
        }

        return false;
    }

}

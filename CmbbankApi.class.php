<?php
/**
 * 招商银行支付类
 * Authors : ZL
 * Date    : 2020-05-09 14:36:11
 */
namespace Org\Util;

class CmbbankApi
{

    private $appid;
    private $publickey;    //公钥
    private $privatekey;   //私钥
    private $cmbpublickey; //招行公钥
    private $secret;
    private $merid;        //商户号
    private $userid;       //收银员

    public function __construct($appid, $publickey, $privatekey, $cmbpublickey, $secret, $merid, $userid)
    {
        $this->appid        = $appid;
        $this->publickey    = $publickey;
        $this->privatekey   = $privatekey;
        $this->secret       = $secret;
        $this->cmbpublickey = $cmbpublickey;
        $this->merid        = $merid;
        $this->userid       = $userid;
    }

    /**
     * 请求支付
     * 2020-05-09 14:36:11
     * 返回二维码链接
     */
    public function Cmbbankpay($money, $attatch, $out_trade_no)
    {

        $total_fee = ($money * 100) . "";

        $data = array();

        $biz_content = array(
            'orderId'      => $out_trade_no, //商户订单号
            'notifyUrl'    => 'https://' . $_SERVER['HTTP_HOST'] . '/Callback/Zacbankpay/notifyurlmessage/',
            'merId'        => $this->merid,  //商户号
            'payValidTime' => '600',         //二维码有效期
            'currencyCode' => '156',
            'userId'       => $this->userid, //收银员
            'txnAmt'       => $total_fee,    //交易金额
            'mchReserved'  => $attatch,      //附带参数
            'tradeScene'   => 'OFFLINE',     //交易场景
            'body'         => '拍照订单',
        );

        $data['biz_content'] = json_encode($biz_content);
        $data['encoding']    = 'UTF-8';
        $data['signMethod']  = '01';
        $data['version']     = '0.0.1';
        //获取签名参数
        $signature = $this->getSign($data);

        $data['sign'] = $signature; //签名
        $ISOPENPAY = C('ISOPENPAY');

        if($ISOPENPAY ==1){
            //正式
            $url = "https://api.cmbchina.com/polypay/v1.0/mchorders/qrcodeapply";
        }else{
            //测试
            $url = "https://api.cmburl.cn:8065/polypay/v1.0/mchorders/qrcodeapply";
        }
        

        $now              = time();
        $str              = array();
        $str['appid']     = $this->appid;
        $str['secret']    = $this->secret;
        $str['sign']      = $signature;
        $str['timestamp'] = $now;

        $_str = '';
        foreach ($str as $k => $v) {
            $_str .= $k . "=" . $v . "&";
        }

        $_str = rtrim($_str, '&');

        $apisign = md5($_str);

        //header头设置
        $headers = array(
            "Content-Type: application/json",
            "appid:" . $this->appid . "",
            "timestamp:" . $now . "",
            "apisign:" . $apisign . "",
        );

        $res = $this->curl_post_https($url, json_encode($data), $headers);

        //同步验签
        $result = $this->verify($res);

        if ($result) {
            //验签成功 返回二维码url

            $qrcode_url = json_decode($res['biz_content'], 1);

            return $qrcode_url['qrCode'];

        } else {
            //验签失败
            exit("验签失败");

        }

    }

    /**
     * 支付业务查询
     * 2020-07-02
     * 定时查询
     */
    public function Cmbbankpay_query($out_trade_no)
    {


        $data = array();

        $biz_content = array(
            'orderId'      => $out_trade_no, //商户订单号
            'merId'        => $this->merid,  //商户号
            'userId'       => $this->userid, //收银员
        );
        $data['biz_content'] = json_encode($biz_content);
        $data['encoding']    = 'UTF-8';
        $data['signMethod']  = '01';
        $data['version']     = '0.0.1';

        //获取签名参数
        $signature = $this->getSign($data);

        $data['sign'] = $signature; //签名

        $ISOPENPAY = C('ISOPENPAY');
        
        if($ISOPENPAY ==1){
            //正式
            $url = "https://api.cmbchina.com/polypay/v1.0/mchorders/orderquery";
        }else{
            //测试
            $url = "https://api.cmburl.cn:8065/polypay/v1.0/mchorders/orderquery";
        }
        
        $now              = time();
        $str              = array();
        $str['appid']     = $this->appid;
        $str['secret']    = $this->secret;
        $str['sign']      = $signature;
        $str['timestamp'] = $now;

        $_str = '';
        foreach ($str as $k => $v) {
            $_str .= $k . "=" . $v . "&";
        }

        $_str = rtrim($_str, '&');

        $apisign = md5($_str);

        //header头设置
        $headers = array(
            "Content-Type: application/json",
            "appid:" . $this->appid . "",
            "timestamp:" . $now . "",
            "apisign:" . $apisign . "",
        );

        $postData = json_encode($data);

        $res = $this->curl_post_https($url, $postData, $headers);

        //验签成功 
        return $res;

       

    }

    /**
     * 拼接需要签名的内容
     * Author: ZL.
     * @param array $data 需签名的字段内容
     */
    private function getSign($data)
    {

        $Parameters = array();
        foreach ($data as $k => $v) {
            $Parameters[$k] = $v;
        }
        //按字典序排序参数
        ksort($Parameters);
        $sign = '';
        foreach ($Parameters as $k => $v) {
            $sign .= $k . "=" . $v . "&";
        }
        $sign = rtrim($sign, '&');

        $_sign = $this->SHA2withRSA($sign);

        return $_sign;
    }

    /**
     * Author:ZL
     * 私钥加密
     * @param [type] $str [description]
     */
    private function SHA2withRSA($str)
    {

        if (!is_string($str)) {
            return null;
        }

        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
        wordwrap($this->privatekey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($str, $sign, $privateKey, OPENSSL_ALGO_SHA256);

        $sign = base64_encode($sign);

        return $sign;
    }

    private function curl_post_https($url, $postData, $headers)
    {

        $curl = curl_init(); 
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);
        curl_setopt($curl, CURLOPT_POST, 1); 
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData); 
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); 
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);
        }
        curl_close($curl); 
        return json_decode($output, true);
    }
    /**
     * Author:ZL
     * 验证签名(同步)
     * @param [type] $str [description]
     */
    private function createLinkstring($para)
    {
        $arg = "";
        while (list($key, $val) = each($para)) {
            $arg .= $key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {$arg = stripslashes($arg);}

        return $arg;
    }

    private function createLinkstringUrlencode($para)
    {
        $arg = "";
        while (list($key, $val) = each($para)) {
            $arg .= $key . "=" . urlencode($val) . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {$arg = stripslashes($arg);}

        return $arg;
    }

    private function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }

    private function verify($params)
    {

        if ($params['returnCode'] == 'SUCCESS' && $params['respCode'] == 'SUCCESS') {

            $sign = $params['sign'];

            unset($params['sign']);

            $params = $this->argSort($params);

            $verify_str = $this->createLinkstring($params);

            $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->cmbpublickey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
            //var_dump(openssl_verify($verify_str, base64_decode($sign), $res, OPENSSL_ALGO_SHA256));

            //调用openssl内置方法验签，返回bool值
            $result = false;

            $result = (openssl_verify($verify_str, base64_decode($sign), $res, OPENSSL_ALGO_SHA256) === 1);

            return $result;

        } else {

            return false;

        }

    }

}

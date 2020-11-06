<?php
/**
 * 西银惠付(西安银行支付类)
 * Authors : ZL
 * Date    : 2019-12-13 09:36:11
 */
namespace Org\Util;

class XacbankApi
{

    private $openpl_appid; //OPENPL_APPID
    private $publickey;    //公钥
    private $privatekey;   //私钥
    private $secret_key;   //OPENPL_APPSECRET

    public function __construct($openpl_appid, $publickey, $privatekey, $secret_key)
    {
        $this->openpl_appid = $openpl_appid;
        $this->publickey    = $publickey;
        $this->privatekey   = $privatekey;
        $this->secret_key   = $secret_key;
    }

    /**
     * 请求支付
     * 2019年12月13日14点07分
     * 返回二维码链接
     */
    public function Xacbankpay($money, $attatch, $out_trade_no)
    {

        $data = array();

        $total_fee            = ($money * 100) . ""; //将金额转化为字符串
        $data['OUT_TRADE_NO'] = $out_trade_no;       //商户订单号
        $data['OPENPL_APPID'] = $this->openpl_appid; //开放平台唯一标识
        $data['TOTAL_FEE']    = $total_fee;          //支付金额
        $data['ATTATCH']      = $attatch;            //附加数据，在支付通知中原样返回

        //获取签名参数
        $signature = $this->sign($data, false);

        $data['SIGNATURE'] = $signature; //签名

        $postData = json_encode($data);

        $url = "https://c.xacbank.com:8000/api/OpenPlatForm/scanNative";

        $res = $this->curl_post_https($url, $postData);

        if ($this->verify($res, false)) {

            return $res;

        } else {

            exit("验签失败！");

        }

    }

    /**
     * 查询交易
     * 2020年07月01日
     * 定时任务
     */
    public function xacbank_query($out_trade_no)
    {

        $data = array();

        $data['OPENPL_APPID'] = $this->openpl_appid; //开放平台唯一标识
        $data['OUT_TRADE_NO'] = $out_trade_no;       //商户订单号
        //获取签名参数
        $signature = $this->sign($data, false);

        $data['SIGNATURE'] = $signature; //签名

        $postData = json_encode($data);

        $url = "https://c.xacbank.com:8000/api/OpenPlatForm/getDealInfo";

        $res = $this->curl_post_https($url, $postData);

        if ($this->verify($res, false)) {

            return $res;

        } else {

            exit("验签失败！");

        }

    }

    private function curl_post_https($url, $postData)
    {
        // 模拟提交数据函数
        $curl = curl_init();                           // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);         // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_POST, 1);           // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);       // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $output = curl_exec($curl);                    // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);          // 捕抓异常
        }
        curl_close($curl);                             // 关闭CURL会话
        return json_decode($output, true);
    }
    /**
     * 数据签名
     *
     * @param array $data 待签名计算的数据
     * @param boolean $pubkey 是否使用公钥签名
     * @return string 返回签名后的字符串
     */
    private function sign(array $data, $pubkey = true)
    {
        if ($pubkey) {
            if (empty($this->publickey)) {
                throw new \Exception('未设置有效的公钥');
            } else if (empty($this->privatekey)) {
                throw new \Exception('未设置有效的私钥');
            }
        }
        if (empty($this->secret_key)) {
            throw new \Exception('未设置有效的秘钥');
        }
        //对数据字典序排序
        \ksort($data);
        //拼接字符串
        $params = [];
        foreach ($data as $key => $value) {
            $value = trim(strval($value));
            if ('' !== $value) {
                $params[] = "{$key}={$value}";
            }
        }
        //拼接成queryString字符串
        $queryStr = \implode('&', $params);
        //使用秘钥进行sha256加密
        $sha256 = \hash_hmac('sha256', $queryStr, $this->secret_key, false);
        if ($pubkey) {
            //使用公钥进行加密
            $result = \openssl_public_encrypt($sha256, $encryptData, $this->publickey);
        } else {
            //使用私钥进行加密
            $result = \openssl_private_encrypt($sha256, $encryptData, $this->privatekey);
        }
        if (false === $result) {
            //加密失败
            throw new \Exception('RSA加密失败');
        }
        //加密后结果进行base64编码后返回
        return \base64_encode($encryptData);
    }

    /**
     * 签名验证
     *
     * @param array $data 待签名计算的数据
     * @param boolean $pubkey 是否使用公钥验证
     * @return boolean 返回签名验证成功与否
     */
    private function verify(array $data, $pubkey = true)
    {
        if ($pubkey) {
            if (empty($this->publickey)) {
                throw new \Exception('未设置有效的公钥');
            } else if (empty($this->privatekey)) {
                throw new \Exception('未设置有效的私钥');
            }
        }
        if (empty($this->secret_key)) {
            throw new \Exception('未设置有效的秘钥');
        }
        //获取签名值
        $signature = $data['SIGNATURE'];
        //删除掉签名值
        unset($data['SIGNATURE']);
        //对数据字典序排序
        \ksort($data);
        //拼接字符串
        $params = [];
        foreach ($data as $key => $value) {
            $value = trim(strval($value));
            if ('' !== $value) {
                $params[] = "{$key}={$value}";
            }
        }
        //拼接成queryString字符串
        $queryStr = \implode('&', $params);
        //使用秘钥进行sha256加密
        $sha256 = \hash_hmac('sha256', $queryStr, $this->secret_key, false);
        if ($pubkey) {
            //使用公钥进行解密
            $result = \openssl_public_decrypt(\base64_decode($signature), $decryptData, $this->publickey);
        } else {
            //使用私钥进行解密
            $result = \openssl_private_decrypt(\base64_decode($signature), $decryptData, $this->privatekey);
        }
        if (false === $result) {
            throw new \Exception('RSA解密失败');
        }
        //$decryptData是对方进行sha256处理后的值
        //$sha256是自己进行计算后的值
        return $decryptData == $sha256;
    }

}

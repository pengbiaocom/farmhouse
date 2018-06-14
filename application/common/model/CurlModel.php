<?php
/**
 * Worker多进程操作类
 * @author onep2p<onep2p@163.com>
 *
 */
namespace app\common\model;

use think\Model;
class CurlModel extends Model{
    protected static $timeout = 10;
    protected static $ch = null;
    protected static $useragent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.79 Safari/537.36';
    protected static $http_raw = false;
    protected static $ssl_peer = false;
    protected static $ssl_host = false;
    protected static $http_location = false;
    protected static $cookie = null;
    protected static $cookie_jar = null;
    protected static $cookie_file = null;
    protected static $referer = null;
    protected static $ip = null;
    protected static $proxy = null;
    protected static $headers = array();
    protected static $hosts = array();
    protected static $gzip = false;
    protected static $info = array();
    
    
    public function __construct(){
        set_time_limit(0);//连接无时间限制
    }   
    

    /**
     * set timeout
     *
     * @param init $timeout
     * @return
     */
    public static function set_timeout($timeout){
        self::$timeout = $timeout;
    }

    /**
     * 设置代理
     * @param mixed $proxy
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public static function set_proxy($proxy){
        self::$proxy = $proxy;
    }

    /**
     * set referer
     *
     */
    public static function set_referer($referer){
        self::$referer = $referer;
    }

    /**
     * 设置 user_agent
     *
     * @param string $useragent
     * @return void
     */
    public static function set_useragent($useragent){
        self::$useragent = $useragent;
    }

    /**
     * 设置COOKIE
     *
     * @param string $cookie
     * @return void
     */
    public static function set_cookie($cookie){
        self::$cookie = $cookie;
    }

    /**
     * 设置COOKIE JAR
     *
     * @param string $cookie_jar
     * @return void
     */
    public static function set_cookie_jar($cookie_jar){
        self::$cookie_jar = $cookie_jar;
    }

    /**
     * 设置COOKIE FILE
     *
     * @param string $cookie_file
     * @return void
     */
    public static function set_cookie_file($cookie_file){
        self::$cookie_file = $cookie_file;
    }

    /**
     * 获取内容的时候是不是连header也一起获取
     * 
     * @param mixed $http_raw
     * @return void
     * @author seatle <seatle@foxmail.com> 
     * @created time :2016-09-18 10:17
     */
    public static function set_http_raw($http_raw){
        self::$http_raw = $http_raw;
    }
    
    /**
     * 是否使用ssl_peer
     * @param unknown $ssl_peer
     */
    public static function set_ssl_peer($ssl_peer){
        self::$ssl_peer = $ssl_peer;
    }
    
    /**
     * 是否使用ssl_host
     * @param unknown $ssl_host
     */
    public static function set_ssl_host($ssl_host){
        self::$ssl_host = $ssl_host;
    }
    
    /**
     * 是否需要跳转
     * @param unknown $http_location
     */
    public static function set_http_location($http_location){
        self::$http_location = $http_location;
    }

    /**
     * 设置IP
     *
     * @param string $ip
     * @return void
     */
    public static function set_ip($ip){
        self::$ip = $ip;
    }

    /**
     * 设置Headers
     *
     * @param string $headers
     * @return void
     */
    public static function set_headers($headers){
        self::$headers = $headers;
    }

    /**
     * 设置Hosts
     *
     * @param string $hosts
     * @return void
     */
    public static function set_hosts($hosts){
        self::$hosts = $hosts;
    }

    /**
     * 设置Gzip
     *
     * @param string $hosts
     * @return void
     */
    public static function set_gzip($gzip){
        self::$gzip = $gzip;
    }

    /**
     * 初始化 CURL【单线程专用】
     * @return resource
     */
    public static function init(){
        if (!is_resource ( self::$ch )){
            self::$ch = curl_init ();
            curl_setopt(self::$ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt(self::$ch, CURLOPT_CONNECTTIMEOUT, self::$timeout );
            curl_setopt(self::$ch, CURLOPT_HEADER, false );
            curl_setopt(self::$ch, CURLOPT_USERAGENT, self::$useragent );
            curl_setopt(self::$ch, CURLOPT_TIMEOUT, self::$timeout + 5);
            // 在多线程处理场景下使用超时选项时，会忽略signals对应的处理函数，但是无耐的是还有小概率的crash情况发生
            curl_setopt(self::$ch, CURLOPT_NOSIGNAL, true);
        }
        
        return self::$ch;
    }

    /**
     * get
     * @param unknown $url
     * @param array $fields
     * @return mixed
     */
    public static function get_single($url, $fields = array()){
        self::init ();
        return self::http_request($url, 'get', $fields);
    }

    /**
     * $fields 有三种类型:1、数组；2、http query；3、json
     * 1、array('name'=>'yangzetao') 2、http_build_query(array('name'=>'yangzetao')) 3、json_encode(array('name'=>'yangzetao'))
     * 前两种是普通的post，可以用$_POST方式获取
     * 第三种是post stream( json rpc，其实就是webservice )，虽然是post方式，但是只能用流方式 http://input 后者 $HTTP_RAW_POST_DATA 获取 
     * 
     * @param mixed $url 
     * @param array $fields 
     * @param mixed $proxy 
     * @static
     * @access public
     * @return void
     */
    public static function post_single($url, $fields = array()){
        self::init ();
        return self::http_request($url, 'post', $fields);
    }

    
    /**
     * 单线程请求处理
     * @param unknown $url
     * @param string $type
     * @param unknown $fields
     * @return mixed
     */
    public static function http_request($url, $type = 'get', $fields){
        // 如果是 get 方式，直接拼凑一个 url 出来
        if(strtolower($type) == 'get' && !empty($fields)) {
            $url = $url . (strpos($url,"?")===false ? "?" : "&") . http_build_query($fields);
        }

        curl_setopt(self::$ch, CURLOPT_URL, $url );
        // 如果是 post 方式
        if(strtolower($type) == 'post'){
            curl_setopt(self::$ch, CURLOPT_POST, true );
            curl_setopt(self::$ch, CURLOPT_POSTFIELDS, $fields);
        }
        
        if(self::$ssl_peer) curl_setopt(self::$ch, CURLOPT_SSL_VERIFYPEER, false);//https
        if(self::$ssl_host) curl_setopt(self::$ch, CURLOPT_SSL_VERIFYHOST, false);//https
        if(self::$http_location) curl_setopt(self::$ch, CURLOPT_FOLLOWLOCATION, self::$http_location);
        if(self::$useragent) curl_setopt(self::$ch, CURLOPT_USERAGENT, self::$useragent);
        if(self::$cookie) curl_setopt(self::$ch, CURLOPT_COOKIE, self::$cookie );
        if(self::$cookie_jar) curl_setopt(self::$ch, CURLOPT_COOKIEJAR, self::$cookie_jar);
        if(self::$cookie_file) curl_setopt(self::$ch, CURLOPT_COOKIEFILE, self::$cookie_file);
        if(self::$referer) curl_setopt(self::$ch, CURLOPT_REFERER, self::$referer);
        if(self::$ip) self::$headers = array_merge( array('CLIENT-IP:'.self::$ip, 'X-FORWARDED-FOR:'.self::$ip), self::$headers);
        if(self::$headers) curl_setopt(self::$ch, CURLOPT_HTTPHEADER, self::$headers);
        if(self::$gzip) curl_setopt(self::$ch, CURLOPT_ENCODING, 'gzip');
        if(self::$proxy) curl_setopt(self::$ch, CURLOPT_PROXY, self::$proxy);
        if(self::$http_raw) curl_setopt(self::$ch, CURLOPT_HEADER, true);
        
        $data = curl_exec(self::$ch);

        //正则匹配cookie  2017-5-26更新获取cookies方式【如雪球网站中会将多个cookie设置在返回头】
        preg_match_all('/Set-Cookie:(.*);/iU',$data,$str);
        if(sizeof($str) > 0) self::$cookie = implode(';', $str[1]);
        self::$info = curl_getinfo(self::$ch);
        curl_close(self::$ch);
        
        return $data;
    }

    
    /**
     * get
     * @param unknown $url
     * @param array $fields
     * @return mixed
     */
    public static function get_multi($urls, $fields = array())
    {
        return self::http_request_multi($urls, 'get', $fields);
    }
    
    
    /**
     * 批量post打开
     * @param unknown $url
     * @param array $fields
     * @return mixed
     */
    public static function post_multi($urls, $fields = array())
    {
        return self::http_request_multi($urls, 'post', $fields);
    }
    
    
    
    /**
     * 批量处理
     * @param array $urls  请求地址
     * @param string $type   请求类型 
     * @param array $fields 请求参数 
     */
    public static function http_request_multi($urls, $type = 'get', $fields){
        $mh = curl_multi_init();
        foreach($urls as $i => $url){
            // 如果是 get 方式，直接拼凑一个 url 出来
            if(strtolower($type) == 'get' && !empty($fields[$i])) {
                $url = $url . (strpos($url,"?")===false ? "?" : "&") . http_build_query($fields[$i]);
            }            
            
            $conn[$i] = curl_init($url);
            curl_setopt($conn[$i], CURLOPT_URL, $url);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, true );
            curl_setopt($conn[$i], CURLOPT_CONNECTTIMEOUT, self::$timeout );
            curl_setopt($conn[$i], CURLOPT_HEADER, false );
            curl_setopt($conn[$i], CURLOPT_USERAGENT, self::$useragent );
            curl_setopt($conn[$i], CURLOPT_TIMEOUT, self::$timeout + 5);
            // 在多线程处理场景下使用超时选项时，会忽略signals对应的处理函数，但是无耐的是还有小概率的crash情况发生
            curl_setopt($conn[$i], CURLOPT_NOSIGNAL, true);
            
            if(strtolower($type) == 'post'){
                curl_setopt($conn[$i], CURLOPT_POST, true );
                curl_setopt($conn[$i], CURLOPT_POSTFIELDS, $fields[$i]);
            }
            if(self::$ssl_peer) curl_setopt($conn[$i], CURLOPT_SSL_VERIFYPEER, false);//https
            if(self::$ssl_host) curl_setopt($conn[$i], CURLOPT_SSL_VERIFYHOST, false);//https
            if(self::$http_location) curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, self::$http_location);
            if(self::$useragent) curl_setopt($conn[$i], CURLOPT_USERAGENT, self::$useragent);
            if(self::$cookie) curl_setopt($conn[$i], CURLOPT_COOKIE, self::$cookie);
            if(self::$cookie_jar) curl_setopt($conn[$i], CURLOPT_COOKIEJAR, self::$cookie_jar);
            if(self::$cookie_file) curl_setopt($conn[$i], CURLOPT_COOKIEFILE, self::$cookie_file);
            if(self::$referer) curl_setopt($conn[$i], CURLOPT_REFERER, self::$referer);
            if(self::$ip) self::$headers = array_merge( array('CLIENT-IP:'.self::$ip, 'X-FORWARDED-FOR:'.self::$ip), self::$headers);
            if(self::$headers) curl_setopt($conn[$i], CURLOPT_HTTPHEADER, self::$headers);
            if(self::$gzip) curl_setopt($conn[$i], CURLOPT_ENCODING, 'gzip');
            if(self::$proxy) curl_setopt($conn[$i], CURLOPT_PROXY, self::$proxy);
            if(self::$http_raw) curl_setopt($conn[$i], CURLOPT_HEADER, true);
        }
        
        do{
            curl_multi_exec($mh,$active);
        }while($active);
        
        $data = array();
        foreach($urls as $i => $url){
            $data[$i] = curl_exec($conn[$i]); // 获得爬取的代码字符串
            self::$info[$i] = curl_getinfo($conn[$i]);
            
            //正则匹配cookie  2017-5-26更新获取cookies方式【如雪球网站中会将多个cookie设置在返回头】
            preg_match_all('/Set-Cookie:(.*);/iU',$data[$i],$str);
            if(sizeof($str) > 0) self::$cookie[$i] = implode(';', $str[1]);
        }
        curl_multi_close($mh);//关闭多线程        
        
        return $data;
    }
    
    /**
     * 获取返回的info信息
     * @return mixed
     */
    public static function get_info()
    {
        return self::$info;
    }

    /**
     * 获取http返回码
     */
    public static function get_http_code()
    {
        return self::$info['http_code'];
    }
    
    /**
     * 获取cookie数据
     */
    public static function get_cookie(){
        return self::$cookie;
    }
}
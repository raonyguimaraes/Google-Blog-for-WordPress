<?php
define('SM2WP_AUTH_URL', 'http://auth.sm2wp.com/');
define('SM2WP_UPDATE_URL', 'http://sm2wp.com/');

if (get_option('timezone_string')) {
    @date_default_timezone_set(get_option('timezone_string'));
}

function log_debug($message) {
    $log = get_option('gfw_debug', array());
    $d = get_option('timezone_string') ? date('d/m/Y @ H:i:s') : date('d/m/Y @ H:i:s', current_time('timestamp'));
    array_unshift($log, '<b>['.$d.']</b> '.$message);
    if (count($log) > 20) array_pop($log);
    update_option('gfw_debug', $log);
}

function log_error($error) {
    $log = get_option('gfw_errors', array());
    array_unshift($log, $error);
    update_option('gfw_errors', $log);
}

function log_info($info) {
    $log = get_option('gfw_info', array());
    array_unshift($log, $info);
    update_option('gfw_info', $log);
}

function log_running($message) {
    $log = get_option('gfw_running', array());
    $d = get_option('timezone_string') ? date('d/m/Y @ H:i:s') : date('d/m/Y @ H:i:s', current_time('timestamp'));
    array_unshift($log, '<b>['.$d.']</b> '.$message);
    if (count($log) > 10) array_pop($log);
    update_option('gfw_running', $log);
}

abstract class SM2WP_Post {

    abstract function get_title();
    abstract function get_content();

}

abstract class SM2WP_Network {

    protected abstract function refresh();

    public static function post($url, $data) {
        $r = null;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'json='.json_encode($data).'&');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $r = curl_exec($ch);
        curl_close($ch);

        list(,$status_code,) = $http_response_header[0];

        return array($status_code, json_decode($r));
    }


    public static function get($url) {
        $r = null;
        $status_code = null;
        mb_internal_encoding('UTF-8');

        if (ini_get('allow_url_fopen') && in_array('https', stream_get_wrappers()))
        {
            $r = @file_get_contents($url);
        }
        else
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $r = curl_exec($ch);
            curl_close($ch);
        }

        #list(,$status_code,) = @explode(' ', $http_response_header[0]);
        $status_code = ($status_code ? $status_code : ($r ? '200' : null));
        log_debug('['.$status_code.']'.$url);
        if (!$status_code) return array(null, null);

        $r = mb_convert_encoding($r, "HTML-ENTITIES", "UTF-8");
        $r = str_replace("\ufeff",'', $r);
        $r = str_replace("&nbsp;",' ', $r);
        return array($status_code, $r);
    }
}

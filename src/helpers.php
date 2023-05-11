<?php
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $needle_len = strlen($needle);
        return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, - $needle_len));
    }
}

if(!function_exists('mb_str_split')){
    function mb_str_split($string = '', $length = 1 , $encoding = null ){
        if (is_null($encoding)) $encoding = mb_internal_encoding();
        $split = [];
        if(!empty($string)){
            $mb_strlen = mb_strlen($string,$encoding);
            for($pi = 0; $pi < $mb_strlen; $pi += $length){
                $substr = mb_substr($string, $pi,$length,$encoding);
                if( !empty($substr)){
                    $split[] = $substr;
                }
            }
        }
        return $split;
    }
}

if(!function_exists('timeCost')){
    function timeCost($last = null){
        static $time;
        if (!$time) {
            $time = microtime(true);
        }
        $now = microtime(true);
        $interval = $now - $time;
        $time = $now;
        return number_format((float)$interval, 14);
    }
}

if(!function_exists('array_shift_assoc_kv')){
    function array_shift_assoc_kv( &$arr ){
        $val = reset( $arr );
        $key = key( $arr );
        $ret = [ $key, $val ];
        unset( $arr[ $key ] );
        return $ret; 
    }
}

if(!function_exists('array_reduce_by_stop_signal')){
    function array_reduce_by_stop_signal($array, $callback, $initial = null) {
        list($k, $v) = array_shift_assoc_kv($array);
        $carry = $callback($initial, $k, $v, $stop);
        if (empty($array) || $stop) {
            return $carry;
        } else {
            return array_reduce_by_stop_signal($array, $callback, $carry);
        }
    }
}

if(!function_exists('mb_substr_r')){
    function mb_substr_r($string, $start, $length = null){
        if (empty($string) || $length === 0) return '';
        
        $T = mb_strlen($string);
        $maxOffset = $T - 1;
        $minOffset = 0;
        
        if ($start < 0) {
            $offsetCutTo = $T + $start;
        } else {
            $offsetCutTo = $start;
        }
        $offsetCutTo = $offsetCutTo > $maxOffset ? $maxOffset : $offsetCutTo;
        $offsetCutTo = $offsetCutTo < $minOffset ? $minOffset : $offsetCutTo;
        if ($length > 0){
            $offsetCutFrom = $offsetCutTo - $length + 1;
        } else {
            $offsetCutFrom = abs($length);
            $length = $offsetCutTo - $offsetCutFrom + 1;
        }
        $offsetCutFrom = $offsetCutFrom < $minOffset ? $minOffset : $offsetCutFrom;
        return mb_substr($string, $offsetCutFrom, $length);
    }
}

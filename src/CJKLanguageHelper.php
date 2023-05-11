<?php
namespace Revhub\Snippet;

/** 
 * https://stackoverflow.com/questions/2598898/detect-cjk-characters-in-php
 */
class CJKLanguageHelper{
    
    public static function isCjk($string) {
        return self::isChinese($string) || self::isJapanese($string) || self::isKorean($string);
    }

    public static function isChinese($string) {
        return preg_match("/\p{Han}+/u", $string);
    }
    
    /**
     * 
     * @param type $string e.g. スローガ
     * @return type
     */
    public static function isJapanese($string) {
        return preg_match('/[\x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $string);
    }
    
    /**
     * 
     * @param type $string e.g. 안녕히계세요
     * @return type
     */
    public static function isKorean($string) {
        return preg_match('/[\x{3130}-\x{318F}\x{AC00}-\x{D7AF}]/u', $string);
    }
}

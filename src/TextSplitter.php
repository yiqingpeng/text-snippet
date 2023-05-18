<?php
namespace Revhub\Snippet;

class TextSplitter
{
    public static function split($splitChars, $text)
    {
        if (is_string($splitChars)) {
            $splitChars = static::splitMbStringToChars($splitChars);
        }
        $i = 0;
        $index = -1;
        $snippet = null;
        $snippets = [];
        do {
            $char = mb_substr($text, $i, 1);
            if ($char === '') {
                $snippets[$index] = $snippet; // on snippet end
                break;
            }
            
            if (is_null($snippet)) {
                $snippet = [];
                $index++;
            }
            $preChar = end($snippet);
            $snippet[] = $char;
            $nextchar = mb_substr($text, $i+1, 1);
            if ($nextchar === '') { // reach the end of text
                $snippets[$index] = $snippet; // on snippet end
                break;
            }
            
            if (in_array($char, $splitChars)) {
                // handle numberic. e.g. 1.3 or 1,000
                if (in_array($char, [',', '.']) && static::isDigit($preChar) && static::isDigit($nextchar)) {
                    $i++;
                    continue;
                }
                $j = $i + 1;
                // process this case "word!!!" or "word,  "
                while (in_array($nextchar, [' ', "\r", "\n"]) || in_array($nextchar, $splitChars)) {
                    $snippet[] = $nextchar;
                    $nextchar = mb_substr($text, ++$j, 1);
                }
                $i = $j;
                $text = mb_substr($text, $i);
                $i = 0;
                $snippets[$index] = $snippet; // on snippet end
                $snippet = null;
            } else {
                $i++;
            }
        } while (true);
        
        $result = [];
        foreach ($snippets as $snippet) {
            $result[] = implode('', $snippet);
        }
        return $result;
    }
    
    public static function splitToWords($splitChars, $text, $noEmpty = false)
    {
        return static::pregSplit($splitChars, $text, $noEmpty ? PREG_SPLIT_NO_EMPTY : 0);
    }

    public static function splitToKeyWords($keyPhrase, $noEmpty = false)
    {
        $phrases = [];
        $singleKeyWords = preg_replace_callback(
            '/"(.*?)"/',
            function ($matches) use (&$phrases) {
                $phrases[] =preg_replace('/[\s　]+/', ' ', $matches[1]);
                return '';
            },
            $keyPhrase
        );
        $keywords = array_merge($phrases, static::pregSplit(['\s+'], $singleKeyWords, $noEmpty ? PREG_SPLIT_NO_EMPTY : 0));
        if ($noEmpty) {
            $keywords = array_filter($keywords);
        }
        return $keywords;
    }
 
    public static function splitMbStringToChars($string, $noEmpty = false)
    {
        return preg_split('/(?<!^)(?!$)/u', $string, $noEmpty ? PREG_SPLIT_NO_EMPTY : 0);
    }
    
    public static function isDigit($char)
    {
        $accii = ord($char);
        return  $accii >= 48 && $accii <= 57;
    }
    
    public static function pregSplit($splitChars, $text, $flags = 0)
    {
        return preg_split('/'.implode('|', $splitChars).'/u', $text, -1, $flags);//PREG_SPLIT_NO_EMPTY
    }
    
    public static function isEmptyString($string)
    {
        return is_null($string) || $string === false || $string === '';
    }

    public static function test()
    {
        $text = " Y（Hi）, chane.10.67 USB2.0 Mem 2,000, 8 什么，吗？？？ 其2.888不！!! Brown & Cast, V6 Edition - By Wilkins, Schutz, & Linduff [Book Only], Led—Mov Spit's,  Gifts (April 1, 2013 - April 7, 2013) Cur / Les, Eats Vol. 2 (3 Pack): Brunch 'n' Lunch, Laurentis V2: Anytime, Living--How, Lesson - 36-Copy. Ahead P.O.S. K";
        echo $text, '<br>';
        $s = microtime(true);
        $words = static::split([".", ',','!',"?",":", "'", '"', "\n", "\r", "\t",'(',')', ' ','-','=', '。', '，', '！', '？', '：', '‘', '“', '”', '’', '&'], $text);
        $e = microtime(true);
        echo "Time: " , ($e-$s) * 1000 ,"<br>";
        //$words = static::splitToWords(['\s*&+\s*', '\s*\/+\s*', '\s*\-+\s*','\s*\:+\s*','\s*，+\s*','\s*？+\s*','\s+'], $text);
        $words = static::pregSplit([
            ',\s+', '，\s*', '\.\s+'
        ], $text);
        $s = $e;
        $e = microtime(true);
        echo "Time: " , ($e-$s) * 1000 , "<br>";
        foreach ($words as $word) {
            echo "<span style='border:1px solid grey;'>", $word, "</span>(", mb_strlen($word) ,")<br>";
        }
    }
}

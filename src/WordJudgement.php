<?php
namespace Revhub\Snippet;

class WordJudgement
{
    protected $usedWords = [];
    public static $repeatAllowedWords = [];
    public static $bannedSuffixWords = [];
    public static $bannedPrefixWords = [];
    
    public static function setRepeatAllowedWords(array $words)
    {
        foreach ($words as $word) {
            $word = trim($word);
            if ($word) {
                static::$repeatAllowedWords[$word] = '';
            }
        }
    }
    
    public static function setBannedSuffixWords(array $words)
    {
        foreach ($words as $word) {
            $word = trim($word);
            if ($word) {
                static::$bannedSuffixWords[$word] = '';
            }
        }
    }
    
    public static function setBannedPrefixWords(array $words)
    {
        foreach ($words as $word) {
            $word = trim($word);
            if ($word) {
                static::$bannedPrefixWords[$word] = '';
            }
        }
    }
    
    public static function inRepeatAllowedWords($word)
    {
        return array_key_exists($word, static::$repeatAllowedWords);
    }
    
    public static function inBannedSuffixWords($word)
    {
        return array_key_exists($word, static::$bannedSuffixWords);
    }
    
    public static function inBannedPrefixWords($word)
    {
        return array_key_exists($word, static::$bannedPrefixWords);
    }
    
    public function setUsedWord($word)
    {
        $this->usedWords[$word] = '';
    }
    
    public function inUsedWords($word)
    {
        return array_key_exists($word, $this->usedWords);
    }

    public static function isNumber($word)
    {
        if (is_numeric($word)) {
            return true;
        }
        
        $decimalLength = strlen(strstr($word, '.', false))-1;
        if ($decimalLength < 0) {
            $decimalLength = 0;
        }
        $numberFormatted = @number_format(str_replace(',', '', $word), $decimalLength);
        return $numberFormatted == $word;
    }
    
    public static function isPercentage($word)
    {
        $numberPart = strstr($word, '%', true);
        return str_ends_with($word, '%') && static::isNumber($numberPart);
    }
    
    public static function isMoney($word)
    {
        $count = 0;
        $numberPart = str_replace('$', '', $word, $count);
        return str_starts_with($word, '$') && $count==1 && static::isNumber($numberPart);
    }
    
    public static function isDate($word)
    {
        if (preg_match('#^[1-2]\d{3}?/(?:0?[1-9]|1[0-2])/(?:0?[1-9]|[12][0-9]|3[01])$#', $word)) {
            return 1;
        } elseif (preg_match('#^(?:0?[1-9]|1[0-2])/(?:0?[1-9]|[12][0-9]|3[01])/\d{2}$#', $word)) {
            return 2;
        } elseif (preg_match('/^[1-2]\d{3}?-(?:0?[1-9]|1[0-2])-(?:0?[1-9]|[12][0-9]|3[01])$/', $word)) {
            return 4;
        } elseif (preg_match('/^(?:0?[1-9]|[12][0-9]|3[01])-(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sept|Oct|Nov|Dec)-\d{2}$/', $word)) {
            return 5;
        }
        return 0;
    }
    
    public static function isFraction($word)
    {
        return preg_match('#^\d+?/[1-9]\d*?$#', $word);
    }
    
    public static function isEmail($word)
    {
        return filter_var($word, FILTER_VALIDATE_EMAIL);
    }
    
    public static function isBadWord($word)
    {
        if (static::isNumber($word) || static::isPercentage($word) || static::isMoney($word) || static::isFraction($word) || static::isDate($word) || static::isEmail($word)) {
            return false;
        }
        if (strpos($word, '@') !== false && !static::isEmail($word)) {
            return true;
        }
        if (strpos($word, '$') !== false && !static::isMoney($word)) {
            return true;
        }
        if (strpos($word, '%') !== false && !static::isPercentage($word)) {
            return true;
        }
        if (strpos($word, '/') !== false && (!static::isFraction($word) && !static::isDate($word))) {
            return true;
        }
        //delete all unbroken 'words' which contain 5 or more numbers combined with characters.
        $matched = preg_match_all('/\d/', $word, $matches);
        if ($matched) {
            $matchesCount = count($matches[0]);
            if ($matchesCount >= 5 && $matchesCount != strlen($word)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     *  like: $99.9  100%  299@ 999 1/4 %999$ etc.
     * @param type $word
     * @return boolean
     */
    public static function isBiasWord($word)
    {
        if (static::isNumber($word) || static::isPercentage($word) || static::isMoney($word) || static::isFraction($word)) {
            return true;
        } else {
            $numberPart = trim(str_replace(['$', '%', '@'], ' ', $word));
            if (static::isNumber($numberPart)) {
                return true;
            }
        }
    }
    
    public static function test()
    {
        static::setRepeatAllowedWords(explode(', ', 'a, à, aan, an, and, auf, av, de, den, di, die, e, een, ein, el, em, en, et, il, is, le, o, och, of, og, on, op, para, på, på, su, sur, the, til, till, to, um, un, und, van, von, y, zu, can, are, was, were, in, from, under, off, his, for, my, our, next, by, about, with, just, that, after, else, or, not, over, at, but'));
        var_dump(static::isBiasWord('44073.6')); // true
        var_dump(static::isBiasWord('$44073.6')); // true
        var_dump(static::isBiasWord('44073.6%')); // true
        var_dump(static::isBiasWord('44073.6@')); // true
        var_dump(static::isBiasWord('@44073.6%')); // true
        var_dump(static::isBiasWord('@440$73.6$')); // true
        var_dump(static::isNumber('44073.6'));//true
        var_dump(static::isNumber('1,000.01'));//true
        var_dump(static::isNumber('111,000,111'));//true
        var_dump(static::isNumber('111,000,111.009'));//true
        var_dump(static::isNumber('110.00000009'));//true
        var_dump(static::isNumber('1,1111.98'));//false
        var_dump(static::isNumber('1-1111.98'));//false
        var_dump(static::isPercentage('120.09%'));
        var_dump(static::isPercentage('p1%'));
        var_dump(static::isMoney('$11,111.111'));
        var_dump(static::isMoney('$11,11.01$12.8'));
        var_dump(static::isFraction('100/999'));
        var_dump(static::isFraction('1/9'));
        var_dump(static::isFraction('100/5555/1000'));
        var_dump(static::isFraction('1/0'));
        var_dump(static::isDate('1/01'));
        var_dump(static::isDate('2012/01/31'));
        var_dump(static::isDate('01/31/84'));
        var_dump(static::isDate('1998-03-31'));
        var_dump(static::isDate('28-Mar-84'));
        var_dump(static::isDate('2001/12/41'));
        echo 'BadWord:<br>';
        var_dump(static::isBadWord('2023/1/5'));
        var_dump(static::isBadWord('1-A1-B24-0'));
        var_dump(static::isBadWord('1999——2-20'));
        var_dump(static::isBadWord('44073.6'));
        $str = 'a good man is very good 人们都 call him as a big big hero. the på hero is the på hero, à à à';
        $words = preg_split('/\s+?/u', $str);
        //var_dump($words);
        $phrase = '';
        $obj = new static;
        foreach ($words as $word) {
            if (static::inRepeatAllowedWords($word)) {
                $phrase .= " $word";
            } else {
                if ($obj->inUsedWords($word)) {
                    continue;
                } else {
                    $obj->setUsedWord($word);
                    $phrase .= " $word";
                }
            }
        }
        echo $phrase;
    }
}

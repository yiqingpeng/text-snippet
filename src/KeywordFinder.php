<?php
namespace Revhub\Snippet;

trait KeywordFinder
{
    public static $highlightTemplate = '<span class="bg-yellow bg-yellow-200">%s</span>';
    
    public static function getKeywordOccurrence($text, $keywords, &$textHighLighted)
    {
        if (empty($keywords)) {
            $textHighLighted = $text;
            return 0;
        }
        if (is_array($keywords)) {
            $regExps = [];
            foreach ($keywords as $keyword) {
                $wordBoundary = CJKLanguageHelper::isCjk($keyword) ? '' : '\b';
                $regExps[] = sprintf('%1$s%2$s%1$s', $wordBoundary, preg_quote($keyword));
            }
        } else {
            $wordBoundary = CJKLanguageHelper::isCjk($keywords) ? '' : '\b';
            $regExps = [sprintf('%1$s%2$s%1$s', $wordBoundary, preg_quote($keywords))];
        }
        
        $regExp = sprintf('~%s~i', implode('|', $regExps));
        $counter = 0;
        $textHighLighted = preg_replace_callback(
            $regExp,
            function ($matches) {
                return sprintf(self::$highlightTemplate, $matches[0]);
            },
            $text,
            -1,
            $counter
        );
        return $counter;
    }
    
    public static function testKeywordFinder()
    {
        $keywords = ['Albanian', '好', '语言', 'the Channel', 'the']; // Find words list
        // $keywords = "Both sides"; // Find a phrase
        $count = KeywordFinder::getKeywordOccurrence(
            'PHP是世界上最好的语言 Senior police and immigration officials on both sides of the Channel are worried by the growing role of Albanian middlemen in facilitating crossings Albanian. Both them are on the same sides. On Both Sides of the channel. On both side of the channel.',
            $keywords,
            $textHighLighted
        );
        echo "<style>.bg-yellow{background-color:yellow;}</style>";
        echo $count, '<br>';
        echo $textHighLighted;
        exit;
    }
}

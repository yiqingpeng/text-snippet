<?php
namespace Revhub\Snippet;

class Snippet{
    use KeywordFinder;
    use TextCleaner;
    
    const THRESHOLD = 0.5;
    public static $maxLength = 600;

    const WORD_SPLITTERS = [".", ',','!',"?",":", "'", '"', "\n", "\r", "\t",'(',')', ' ','-','=', '。', '，', '！', '？', '：', '‘', '“', '”', '’'];
    
    protected $keywords = [];
    
    protected $suffix = '...';

    protected $originalText;
    protected $language;
    protected $plainText;
    protected $charCountOfFullText;

    protected $truncatedCount;

    protected $words;
    protected $snippet;
    
    protected $debugSteps = [];

    public function __construct($originalText, $language, $keyphrase = null) {
        $this->originalText = $originalText;
        $this->language = $language;
        if ($keyphrase) {
            $wordsofPhrase = TextSplitter::splitToWords(['\s+'], $keyphrase, true);
            $this->keywords = static::cleanupKeywords($wordsofPhrase, $this->language);
        }
        $this->initialize();
    }
    
    public static function cleanupKeywords($keywords, $language){
        PhraseBuilder::loadSpecialWords();
        $allowRepeatWords = PhraseBuilder::getAllowedRepeatWords($language);
        WordJudgement::setRepeatAllowedWords($allowRepeatWords);
        $keywordsKept = [];
        foreach ($keywords as $word) {
            $word = strtolower(trim($word));
            if (WordJudgement::inRepeatAllowedWords($word)) continue;
            $keywordsKept[] = $word;
        }
        return $keywordsKept;
    }
    
    protected function initialize(){
        $this->plainText = $this->stripAllTags($this->originalText, true);
        $this->charCountOfFullText = static::getCharCount($this->plainText);
        $this->truncatedCount = $this->getTruncatedCountDefault();
        $this->words = static::splitToWords($this->plainText);
    }

    protected function getTruncatedCountDefault() {
        return min(static::$maxLength, floor($this->charCountOfFullText * static::THRESHOLD)); // default value to truncatedCount
    }
    
    public static function splitToWords($text, $caseInsensitive = true){
        $string = str_replace(static::WORD_SPLITTERS, ' ', $text);
        if ($caseInsensitive) {
            $string = strtolower($string);
        }
        return array_values(array_filter(explode(' ', $string)));
    }
    
    public function getSnippet($truncateLength = null, $withApostrophe = true){
        $snippet = $this->process($truncateLength);
        $snippet = static::stripSuffixChars($snippet);
        $this->setSnippet($snippet); // save clean snippet text
        
        return $this->decorate($snippet, $withApostrophe);
    }
    
    protected function decorate($snippet, $withApostrophe = true) {
        if (method_exists($this, 'highlight')) {
            $snippet = $this->highlight();
        }
        if($withApostrophe) {
            $snippet = $this->withApostrophe($snippet);
        }
        return $snippet;
    }

    public static function stripSuffixChars($text){
        //$suffixCharsStriped = "/('|\"|\:|\s|\-|\=|\(|‘|“)+?$/";
        $suffixCharsStriped = "/\s+?$/";
        return preg_replace($suffixCharsStriped, '', $text);
    }
    
    protected function setSnippet($snippet) {
        $this->snippet = $snippet;
    }
    
    protected function highlight(){
        $keywordCount = $this->getKeywordOccurrence($this->snippet, $this->keywords, $highligtedText);
        $this->recordDebugStep('Highlighted text' . "(keywords: $keywordCount)", $this->snippet, $highligtedText);
        return $highligtedText;
    }
    
    protected function withApostrophe($snippet){
        if ($this->getSnippetCharCount() != $this->getCharCountOfFullText()) {
            $snippet .= $this->suffix;
        }
        return $snippet;
    }
    
    protected function process($truncateLength = null){
        if (count($this->words) <= 1) {
            $snippet =  $this->plainText;
        } else {
            $snippet = $this->createBasicSnippet($truncateLength);
        }
        return $snippet;
    }
    
    protected function createBasicSnippet($truncatedCount = null){
        $truncatedCount = $truncatedCount ?: $this->getTruncatedCount();
        $snippet = static::substringWithoutWordBreaking($this->plainText, 0, $truncatedCount, $trace);
        foreach ($trace as $msg) {
            $this->recordDebugStep($msg[0], $msg[1]);
        }
        if (static::isEmptyChar($snippet)) {
            $snippet = $this->words[0];
        }
        $this->recordDebugStep("The snippet is empty after truncated then take the first word as the snippet", $snippet);
        return $snippet;
    }
    
    protected function recordDebugStep($step, $string, $stringModified = ''){
        $this->debugSteps[] = [
            'step' => $step,
            'str' => $string,
            'strM' => $stringModified
        ];
    }

    public function getSnippetCharCount(){
        return static::getCharCount($this->snippet);
    }
    
    public function getCharCountOfFullText(){
        return $this->charCountOfFullText;
    }
    
    public function setTruncatedCount($truncatedCount = null) {
        if ($truncatedCount <= 0) {
            $truncatedCount = $this->getTruncatedCountDefault();
        }
        $this->truncatedCount = $truncatedCount;
        return $this;
    }
    
    public function getTruncatedCount(){
        return $this->truncatedCount;
    }

    public function __toString() {
        return $this->getSnippet();
    }
    
    public function printDebugInfo(){
        $this->printDebugInfoCommon();
        echo "<ol>";
        foreach ($this->debugSteps as $item) {
            echo sprintf('<li style="color:#185abc;">[ %s ]:</li>', $item['step']);
            echo sprintf('<li style="padding:4px;">&gt;&gt; %s<i style="margin-left:5px;">(length: %d)</i></li>', 
                    $item['strM'] ?: $item['str'],
                    static::getCharCount($item['str']));
        }   
        echo "</ol>";
    }
    
    protected function printDebugInfoCommon(){
        echo "<p>Keywords: <b>" . implode(', ', $this->keywords) . "</b><p>";
        echo "<p>Truncated Count: <b>" . $this->getTruncatedCount() . "</b><p>";
    }
    
    public static function isDigit($char){
        $accii = ord($char);
        return  $accii >= 48 && $accii <= 57;
    }
    
    public static function isEmptyChar($char){
        return is_null($char) || $char === false || $char === '';
    }
    
    public static function getCharCount($text) {
        return mb_strlen($text);
    }
    
    /**
     * func("abcdefghijk", 0, 5) => "" ??? 
     * @param type $string
     * @param int $start
     * @param type $length
     * @param type $trace
     * @return string
     */
    public static function substringWithoutWordBreaking($string, $start = 0, $length = null, &$trace = []) {
        if (!is_null($length) && $length <= 0) {
            return '';
        }
        if ($start < 0) {
            $start = 0;
        }
        $trace[] = ["Before truncating: ", $string];
        $truncatedString = mb_substr($string, $start, $length);
        $trace[] = ["Truncated from $start to $length: ", $truncatedString];
        $charOnBreakpoint = mb_substr($truncatedString, -1, 1);
        $trace[] = ["The last char (LC) of the truncated string: {{$charOnBreakpoint}}", $truncatedString];
        if (!in_array($charOnBreakpoint, static::WORD_SPLITTERS)){// The last char is not punctuation mark or space
            $charAfterBreakpoint = mb_substr($string, $length, 1);
            $trace[] = ["The next char(NC) to LC: {{$charAfterBreakpoint}}", $truncatedString];
            if (!static::isEmptyChar($charAfterBreakpoint) && !in_array($charAfterBreakpoint, static::WORD_SPLITTERS)) {
                
                // Breaking point happens in a word. we should ignore the broken part.
                $wordsInTruncatedString = static::splitToWords($truncatedString, false);
                $brokenPart = array_pop($wordsInTruncatedString);
                $trace[] = ["Detected the break point happened in a word, so the broken part should be removed", $truncatedString];
                if (!empty($brokenPart)) {
                    $length = mb_strrpos($truncatedString, $brokenPart);
                    $truncatedString = mb_substr($truncatedString, 0, $length);
                    $trace[] = ["Removed the broken part($brokenPart): ", $truncatedString];
                }
            }
        }
              
        return $truncatedString;
    }
}

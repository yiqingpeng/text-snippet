<?php
namespace Revhub\Snippet;

class PhraseBuilder{
    
    const DEFAULT_LANGUAGE = 'en_US';
    
    public static $availableLanguages = [
        'da_DK',
        'de_AT',
        'de_CH',
        'de_DE',
        'en_AU',
        'en_IE',
        'en_NZ',
        'en_US',
        'es_ES',
        'fi_FI',
        'fr_FR',
        'it_IT',
        'ja_JP',
        'nl_BE',
        'nl_NL',
        'pl_PL',
        'pt_PT',
        'sv_SE',
    ];
    
    protected $originalText;
    
    protected $text;
    
    protected $illegalChars = '<>|^=:~(){}[]*?!#"¿¡';
    
    protected $childTextSplitChars = [
        ',\s+', '，\s*', //'\p{Pf}+' 
    ];
    
    protected $wordSplitChars = [
        '\s+', '—+', '©+' //'\p{Mc}+' 
    ];
    
    protected $maxPhraseLength = 40;
    
    protected $language;
    
    protected static $specialWords;
    
    public function __construct($options = null) {
        
        if (!empty($options['illegalChars'])) {
            $illegalChars = is_array($options['illegalChars']) ? implode('', $options['illegalChars']) : (string)$options['illegalChars'];
            $this->setIllegalChars($illegalChars);
        }
        if (!empty($options['childTextSplitChars'])) {
            $this->setChildTextSplitChars($options['childTextSplitChars']);
        }
        if (!empty($options['wordSplitChars'])) {
            $this->setWordSplitChars($options['wordSplitChars']);
        }
        if (!empty($options['maxPhraseLength'])) {
            $this->setMaxPhraseLength($options['maxPhraseLength']);
        }
        $this->setLanguage(static::DEFAULT_LANGUAGE);
        static::loadSpecialWords();
    }
    
    public static function loadSpecialWords(){
        if (empty(static::$specialWords)) {
            static::$specialWords = parse_ini_file(__DIR__ .'/../data/special-words.ini', true);
        }
    }
    
    public static function getAllowedRepeatWords($language){
        static $words;
        if (!$words){
            $words = static::getSpecialWordsInLanguage($language, 'allowed_duplicate');
        }
        return $words;
    }
    
    public static function getBannedSuffixWords($language){
        static $words;
        if (!$words) {
            $words = static::getSpecialWordsInLanguage($language, 'banned_suffix');
        }
        return $words;
    }
    
    public static function getBannedPrefixWords($language){
        static $words;
        if (!$words) {
            $words = static::getSpecialWordsInLanguage($language, 'banned_prefix');
        }
        return $words;
    }
    
    protected static function getSpecialWordsInLanguage($language, $type){
        $wordsSeq = static::$specialWords['en'][$type] ?: '';
        $shortLang = strstr($language, '_', true);
        if (!empty(static::$specialWords[$shortLang][$type])) {
            $wordsSeq .= ', ' . static::$specialWords[$shortLang][$type];
        }
        if (!empty(static::$specialWords[$language][$type])) {
            $wordsSeq .= ', ' . static::$specialWords[$language][$type];
        }
        $wordsArr = static::splitStringByComma($wordsSeq);
        return array_unique($wordsArr);
    }
    
    public static function splitStringByComma($string){
        return preg_split('/\s*,\s*/u', $string, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    public function setIllegalChars($illegalChars){
        $this->illegalChars = $illegalChars;
        return $this;
    }

    public function setChildTextSplitChars(array $childTextSplitChars){
        $this->childTextSplitChars = $childTextSplitChars;
        return $this;
    }
    
    public function setWordSplitChars(array $wordSplitChars){
        $this->wordSplitChars = $wordSplitChars;
        return $this;
    }
    
    public function setMaxPhraseLength($maxPhraseLength){
        $this->maxPhraseLength = $maxPhraseLength;
        return $this;
    }
    
    public function setLanguage($language) {
        $this->language = $language;
        return $this;
    }

    public function process($text){
        $this->prepareProcess();
        $this->originalText = $text;
        $childTexts = $this->cleanupSymbols()->splitToChildTexts();
        $phrases = [];
        foreach ($childTexts as $childText) {
            if (TextSplitter::isEmptyString($childText)) continue;
            $childTextObj = new ChildText($childText, ['wordSplitChars' => $this->wordSplitChars]);
            $childTextObj->setMaxLengthOfPhrase($this->maxPhraseLength)->buildPhrases();
            $phrases = array_merge($phrases, $childTextObj->getPhrases());
            unset($childTextObj);
        }
        return $phrases;
    }
    
    protected function prepareProcess(){
        WordJudgement::setRepeatAllowedWords(static::getAllowedRepeatWords($this->language));
        WordJudgement::setBannedSuffixWords(static::getBannedSuffixWords($this->language));
        WordJudgement::setBannedPrefixWords(static::getBannedPrefixWords($this->language));
    }
    
    public function cleanupSymbols(){
        $text = static::toLowerCase($this->originalText);
        $text = static::entityDecode($text);
        $text = static::replaceCommaOfDateWithSpace($text);
        $text = static::replaceSymbolsWithCTSC($text);
        $text = static::removeIllegalSymbols($text, $this->illegalChars);
        $text = static::replaceSymbolsWithWSC($text);
        $text = static::processSequentialMixedSymbols($text);
        $this->text = $text;
        return $this;
    }
    
    /**
     * Step 1
     * @param type $string
     */
    public static function toLowerCase($string){
        return mb_strtolower($string);
    }
    
    /**
     * Step 2
     * @param type $string
     * @return type
     */
    public static function entityDecode($string){
        return html_entity_decode($string);
    }
    
    /** 
     * Step 3
     * @param type $string
     * @return type
     */
    public static function replaceCommaOfDateWithSpace($string){
        $regExp = '/((Jan(uary)?|Feb(ruary)?|Mar(ch)?|Apr(il)?|May|Jun(e)?|Jul(y)?|Aug(ust)?|Sept(ember)?|Oct(ober)?|Nov(ember)?|Dec(ember)?)\s+(0?[1-9]|[12][0-9]|3[01])),\s?(?=(1\d{3}|20\d{2})\D)/i';
        return preg_replace($regExp, '${1} ', $string);
    }
    
    /**
     * Step 4
     * @param type $string
     * @param type $CTSC = child text split char.
     * @return type
     */
    public static function replaceSymbolsWithCTSC($string, $CTSC = ', ') {
        return preg_replace('/;+?|\.\s+|\s+,/', $CTSC, $string);
    }
    
    /**
     * Step 5
     * @param type $string
     * @param type $symbols
     * @return type
     */
    public static function removeIllegalSymbols($string, $symbols = '<>|^=:~(){}[]*?!#"'){
        return str_replace(TextSplitter::SplitMbStringToChars($symbols), '', $string);
    }
    
    /** 
     * Step 6
     * @param type $string
     * @param type $WSC = word split char
     */
    public static function replaceSymbolsWithWSC($string, $WSC = ' ') {
        $patterns = [
            '#,{2,}|\.{2,}|-{2,}|&+|%{2,}|\${2,}|\++|@{2,}|\'{2,}|/{2,}#',
            '#\s+-|-\s+|\s+\'|\'\s+|\s+/|/\s+|\s+\.\s+#',
        ];
        $string = preg_replace($patterns, $WSC, $string);
        $pattern2 = '#(?<=^|\s)([a-z]+?)[,/]([a-z]+)(?=$|\s|,|;|-|\.)#i';
        $string = preg_replace($pattern2, '$1'.$WSC.'$2', $string);
        $pattern3 = '#(?<=^|\s)([\d]+?)/([a-z]+)(?=$|\s|,|;|-|\.)#i';
        $string = preg_replace($pattern3, '$1'.$WSC.'$2', $string);
        $pattern4 = '#(?<=^|\s)([a-z]+?)/([\d]+)(?=$|\s|,|;|-|\.)#i';
        $string = preg_replace($pattern4, '$1'.$WSC.'$2', $string);
        return $string;
    }
    
    /**
     * Step7
     */
    public static function processSequentialMixedSymbols($string, $symbols = ",.-%$@'/"){
        $symbolsPregSafe = preg_quote($symbols);
        $pattern = "#(?<=^|\s)([a-z]+?)[$symbolsPregSafe][$symbolsPregSafe]+([a-z]+)(?=$|\s|,|;|-|(\. ))#";
        $string = preg_replace($pattern, ' ', $string);
        return $string;
    }
    /*
    protected function splitToChildTexts(){
        return mb_split("[". $this->childTextSplitChars ."]", $this->text);
    }*/
    
    protected function splitToChildTexts(){
        return TextSplitter::pregSplit($this->childTextSplitChars, $this->text, true);
    }
    
    public function printPhrases($phrases){
        foreach ($phrases as $phrase) {
            echo "<p class='row-phrase'><span>", $phrase, "</span> (Length:", mb_strlen($phrase), ")</p>";
        }
    }
}

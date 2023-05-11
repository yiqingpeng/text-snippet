<?php
namespace Revhub\Snippet;

class Phrase{
    const MIN_LENGTH = 12;
    
    protected $lastChar;
    protected $beforeLastChar;
    public $chars = [];
    public $charsCount = 0; // Excluding "\r\n"
    public $kwCount = 0;
    public $highlightedText;
    
    public function pushChar($char){
        $this->beforeLastChar = end($this->chars);
        $this->chars[] = $char;
        $this->lastChar = $char;
        if ($char!=='' && !in_array($char, ["\r", "\n"])) {
            $this->charsCount++;
        }
        return $this;
    }
    
    public function getLastChar(){
        return $this->lastChar;
    }
    
    public function getBeforeLastChar(){
        return $this->beforeLastChar;
    }
    
    public function getCharsCount(){
        return $this->charsCount;
    }
    
    public function getKwCount(){
        return $this->kwCount;
    }
    
    public function isTooShort(){
        return $this->charsCount < static::MIN_LENGTH;
    }
    
    public function getText($highlighted = false){
        if ($highlighted) {
            return $this->highlightedText;
        }
        return implode('', $this->chars);
    }
    
    public function __toString() {
        return $this->getText();
    }
}
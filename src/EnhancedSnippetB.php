<?php
namespace Revhub\Snippet;

class EnhancedSnippetB extends Snippet
{
    
    const PHRASE_ENDINGS = ['.', '!', '?', ',', '。', '！', '？', '，'];
    
    protected $suffix = '';
    protected $phrases = [];
    protected $phraseSequences = [];
    protected $candidatePhraseSequences = [];
    protected $snippets = [];
    protected $indexPicked = 0;
    protected $foundKeyword = false;
    public $timeCost = [];
    
    protected function process()
    {
        if (count($this->words) <= 1) {
            $this->createBasicSnippet();
        } else {
            if (empty($this->keywords)) {
                $this->createBasicSnippet();
            } else {
                timeCost();
                $this->splitToPhrases();
                $this->timeCost['Split the text to phrases'] = timeCost();
                if (!$this->foundKeyword) {
                    $this->createBasicSnippet();
                } else {
                    $this->phrasesToSnippet();
                }
            }
        }
        return $this->snippets[$this->indexPicked]['snippet'];
    }
    
    protected function phrasesToSnippet()
    {
        timeCost();
        $this->buildPhraseSequences();
        $this->timeCost['Build one-jump phrase chains'] = timeCost();
        //echo 'Build time:', $time2-$time1, "S<br>";
        $this->generateCandidatePhraseSequences(); // heavy
        $this->timeCost['Choose eligible phrase chains as candidate'] = timeCost();
        //echo 'Generate candiate time:', $time3-$time2, "S<br>";
        $this->findTargetSnippet();
        $this->timeCost['Find the target phrase chain'] = timeCost();
        //echo 'Find target time:', $time4-$time3, "S<br>";
    }
    
    protected function splitToPhrases()
    {
        $text = $this->plainText;
        $i = 0;
        $index = -1;
        $phrase = null;
        do {
            $char = mb_substr($text, $i, 1);
            if ($char === '') {
                $this->onPhraseEnd($index);
                break;
            }
            
            if (is_null($phrase)) {
                // Get previous $phrase
                $this->onPhraseEnd($index);
                $phrase = new Phrase();
                $index++;
                $this->setPhrase($index, $phrase);
            }
            $phrase->pushChar($char);
            $nextchar = mb_substr($text, $i+1, 1);
            if ($nextchar === '') { // reach the end of text
                $this->onPhraseEnd($index);
                break;
            }
            
            if ($phrase->isTooShort()) {
                $i++;
                continue;
            }

            $lastChar = $phrase->getLastChar();
            
            if (in_array($lastChar, static::PHRASE_ENDINGS)) { // process this case "word!!!" or "word,  "
                if (in_array($lastChar, [',', '.']) && static::isDigit($phrase->getBeforeLastChar()) && static::isDigit($nextchar)) {
                    // handle numberic. e.g. 1.3 or 1,000
                    $i++;
                    continue;
                }
                $j = $i + 1;
                while (in_array($nextchar, [' ', "\r", "\n"]) || in_array($nextchar, static::PHRASE_ENDINGS)) {
                    $phrase->pushChar($nextchar);
                    $nextchar = mb_substr($text, ++$j, 1);
                }
                $i = $j;
                $text = mb_substr($text, $i);
                $i = 0;
                $phrase = null;
            } else {
                $i++;
            }
        } while (true);
    }
    
    protected function onPhraseEnd($index)
    {
        $phrase = $this->getPhrase($index);
        if ($phrase) {
            $phrase->kwCount = static::getKeywordOccurrence($phrase->getText(), $this->keywords, $highlighted);
            $phrase->highlightedText = $highlighted;
            if ($phrase->kwCount > 0) {
                $this->foundKeyword = true;
            }
            unset($phrase);
        }
    }
    
    protected function createBasicSnippet()
    {
        $snippet = parent::createBasicSnippet(); // Get pure snippet.
        $occurrence = 0;
        $highlighted = $snippet;
        if ($this->keywords) {
            $occurrence = static::getKeywordOccurrence($snippet, $this->keywords, $highlighted);
        }
        $msg = count($this->words) <= 1 ? '(Original text has only one word)' : '(Keywords not found/not present)';
        $this->snippets[0] = [
            'snippet' => $snippet,
            'snippetHighlighted' => $highlighted,
            'occurrence' => $occurrence,
            'seq' => 'Basic snippet' . $msg
        ];

        $this->indexPicked = 0;
        $this->suffix = '...';
    }
    
    protected function buildPhraseSequences()
    {
        $truncatedCount = $this->getTruncatedCount();
        $phraseList = $this->phrases;
        $sequences = static::generateOneJumpSequences($phraseList, function ($carry, $position, $phraseIndex, &$stop, $jumpStartPosition, $jumpSteps, &$seq) use ($phraseList, $truncatedCount) {
            if ($position <= $jumpStartPosition || $position >= $jumpStartPosition+$jumpSteps) {
                $carry = (int)$carry + $phraseList[$phraseIndex]->getCharsCount();
                if ($carry >= $truncatedCount) {
                    $stop = true;
                }
                $seq[] = $phraseIndex;
            }
            return $carry;
        });
        $this->phraseSequences = $sequences;
        unset($sequences);
        return $this;
    }
    
    public static function generateOneJumpSequences($list, callable $callback)
    {
        $indexList = array_keys($list);
        $sequences = [];
        while ($indexList) {
            $jumpStartPosition = 0;
            $maxPosition = count($indexList);
            while ($jumpStartPosition < $maxPosition) {
                $jumpSteps = 0;
                while ($jumpStartPosition + $jumpSteps < $maxPosition) {
                    $seq = [];
                    $stop = false;
                    array_reduce_by_stop_signal($indexList, function ($carry, $i, $index, &$stop) use ($callback, &$seq, $jumpStartPosition, $jumpSteps) {
                        $carryRetuned = $callback($carry, $i, $index, $stop, $jumpStartPosition, $jumpSteps, $seq);
                        return $carryRetuned;
                    }, '');
                    $sequences[implode('-', $seq)] = $seq;
                    $jumpSteps++;
                }
                $jumpStartPosition++;
            }
            array_shift($indexList);
        }
        return $sequences;
    }
    
    protected function generateCandidatePhraseSequences()
    {
        // make the snippet length to fit the expected
        $limitCount = $this->getTruncatedCount();
        $sequenceToSnippet = [];
        foreach ($this->phraseSequences as $seq => $phraseIndexes) {
            $snippet = static::parseSequenceToSnippet($phraseIndexes, $limitCount, $this->phrases, $this->keywords);
            if ($snippet['occurrence'] > 0) {
                $snippet['seq'] = $seq;
                $sequenceToSnippet[$seq] = $snippet;
            }
        }
        
        $this->candidatePhraseSequences = $sequenceToSnippet;
        unset($sequenceToSnippet);
    }
    
    /**
     * Change phrase sequence like "1-3-4-5" to snippet object{snippet; charCount; snippetHighlighted; occurrence}
     * @param type $phraseIndexes
     * @param type $snippetLength
     * @param array $phraseList
     * @param type $keywords
     * @return type
     */
    public static function parseSequenceToSnippet($phraseIndexes, $snippetLength, array $phraseList, $keywords)
    {
        $lastPhraseIndex = count($phraseList) - 1;
        $plainText = '';
        $textWithMidApostrophe = '';
        $last = count($phraseIndexes) - 1;
        $counter = 0;
        $kwCount = 0;
        $charCount = 0;
        $isCutted = false;
        $prefix = '';
        $suffix = '...';
        
        foreach ($phraseIndexes as $i => $index) {
            $phraseObj = $phraseList[$index];
            $counter += $phraseObj->getCharsCount();
            if ($counter <=  $snippetLength) {
                $phraseText = $phraseObj->getText();
                $kwCount += $phraseObj->getKwCount();
                $charCount += $phraseObj->getCharsCount();
                $textWithMidApostrophe .= $phraseObj->highlightedText;
            } else {
                $keptCount = $snippetLength - $charCount;
                if ($keptCount > 0) {
                    $keywordPosition = static::searchFirstPosition($phraseObj->getText(), $keywords, $keywordHitted);
                    $phraseText = static::substringWithoutWordBreaking($phraseObj->getText(), 0, max($keptCount, $keywordPosition + static::getCharCount($keywordHitted)));
                    $realCount = static::getCharCount($phraseText);
                    $charCount += $realCount;

                    $_kwCount = static::getKeywordOccurrence($phraseText, $keywords, $_textHighLighted);
                    $kwCount +=  $_kwCount;
                    $textWithMidApostrophe .= $_textHighLighted;
                    if ($realCount < $phraseObj->getCharsCount()) {
                        $isCutted = true;
                    }
                }
            }
            
            $plainText .= $phraseText;
            
            if ($i == 0 && $index > 0) {
                $prefix = '...';
            }
            
            if ($i == $last && $index == $lastPhraseIndex && !$isCutted) {
                $suffix = '';
            }
            
            if ($counter >  $snippetLength) {
                break;
            }
            
            if ($i < $last) {
                $nextIndex = $phraseIndexes[$i+1];
                if ($index + 1 != $nextIndex) {
                    $textWithMidApostrophe .= ' ... ';
                }
            }
        }
        
        return [
            'snippet' => $plainText,
            'charCount' => $charCount,
            'snippetHighlighted' => $prefix . $textWithMidApostrophe . $suffix,
            'occurrence' => $kwCount,
        ];
    }
    
    protected static function searchLastPosition($text, $keywords, &$keywordHitted = '')
    {
        $maxPos = -1;
        foreach ($keywords as $keyword) {
            $pos = mb_strrpos($text, $keyword);
            if (false === $pos) {
                continue;
            }
            if ($pos > $maxPos) {
                $maxPos = $pos;
                $keywordHitted = $keyword;
            }
        }
        
        return $maxPos;
    }
    
    protected static function searchFirstPosition($text, $keywords, &$keywordHitted = null)
    {
        $minPos = -1;
        foreach ($keywords as $keyword) {
            $pos = mb_strpos($text, $keyword);
            if (false === $pos) {
                continue;
            }
            if ($minPos == -1 || $pos < $minPos) {
                $minPos = $pos;
                $keywordHitted = $keyword;
            }
        }
        
        return $minPos;
    }
    
    /**
     * Find the snippet which has the most keyword occurrence.
     */
    protected function findTargetSnippet()
    {
        $max = 0;
        $i = 0;
        foreach ($this->candidatePhraseSequences as $seq => $snippet) {
            if ($snippet['occurrence'] > $max) {
                 $this->indexPicked = $i;
                 $max = $snippet['occurrence'];
            }
            $this->snippets[$i++] = $snippet;
        }
    }
    
    protected function getPhrase($index)
    {
        if (!isset($this->phrases[$index])) {
            return null;
        }
         return $this->phrases[$index];
    }
    
    protected function setPhrase($index, $phrase)
    {
        $this->phrases[$index] = $phrase;
        return $this;
    }
    
    protected function highlight()
    {
        return $this->snippets[$this->indexPicked]['snippetHighlighted'];
    }
    
    public function printDebugInfo()
    {
        $this->printDebugInfoCommon();
        echo "<p>Phrases</p>";
        echo "<ul>";
        foreach ($this->phrases as $i => $phrase) {
            echo sprintf(
                '<li class="phrase-%d" style="padding:5px;">%d. <b style="color:red;">{</b>%s<b style="color:red;">}</b><i style="margin-left:5px;">(length: %d, keywords: %d)</i></li>',
                $i,
                $i,
                $phrase->getText(true),
                $phrase->getCharsCount(),
                $phrase->kwCount
            );
        }
        echo "</ul>";
        
        if (!empty($this->timeCost)) {
            echo "<p>Time Cost</p>";
            echo "<ol>";
            foreach ($this->timeCost as $desc => $time) {
                echo "<li>$desc: <i style='color:red'>".$time."</i> S</li>";
            }
            echo "</ol>";
        }
        
        echo "<p>Snippets</p>";
        echo "<ol>";
        $selectedSeq = '';
        foreach ($this->snippets as $i => $item) {
            if ($i == $this->indexPicked) {
                $selectedSeq = $item['seq'];
            }
            echo sprintf(
                '<li class="%s" style="padding:5px;">[%s] <b style="color:red;">{</b>%s<b style="color:red;">}</b><i style="margin-left:5px;">(length: %d, keywords: <span style="color:red;">%d</span>)</i></li>',
                $i == $this->indexPicked ? 'row-highlight' : '',
                $item['seq'],
                $item['snippetHighlighted'],
                static::getCharCount($item['snippet']),
                $item['occurrence']
            );
        }
        echo "</ol>";
        echo "<style>";
        foreach (explode('-', $selectedSeq) as $phraseOffset) {
            echo ".phrase-$phraseOffset{background-color: rgb(0, 204, 255)}";
        }
        echo "</style>";
    }
}

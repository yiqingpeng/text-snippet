<?php
namespace Revhub\Snippet;

class EnhancedSnippetC extends EnhancedSnippetB
{
    
    protected $phraseKeywordsCount = [];
    
    protected $phraseCharsCount = [];
    
    protected $phrasesClamped = [];
    
    protected $phraseSidecarOnHead = [];
    
    protected $phraseSidecarOnEnd = [];
    
    protected $phrasesSidecar = [];
    
    protected $phrasesUnclassed = [];
    
    protected function phrasesToSnippet()
    {
        timeCost();
        $lastPhraseId = count($this->phrases) - 1;
        foreach ($this->phrases as $i => $phrase) {
            if (array_key_exists($i, $this->phraseKeywordsCount)) {
                continue;
            }
            $leftPhrase = $this->getPhrase($phrase->left);
            $rightPhrase = $this->getPhrase($phrase->right);
            $keywordOccurrenceOnLeft = $leftPhrase && $leftPhrase->kwCount > 0;
            $keywordOccurrenceOnRight = $rightPhrase && $rightPhrase->kwCount > 0;
            if ($keywordOccurrenceOnLeft && $keywordOccurrenceOnRight) {
                $this->phrasesClamped[] = $i;
            } else if ($keywordOccurrenceOnLeft || $keywordOccurrenceOnRight) {
                if ($keywordOccurrenceOnLeft && $lastPhraseId == $i) {
                    $this->phraseSidecarOnEnd[] = $i;
                } else if ($keywordOccurrenceOnRight && $i == 0) {
                    $this->phraseSidecarOnHead[] = $i;
                } else {
                    $this->phrasesSidecar[] = $i;
                }
            } else {
                $this->phrasesUnclassed[] = $i;
            }
        }
        //var_dump($this->phrasesClamped, $this->phrasesSidecar, $this->phrasesUnclassed);
        $max = max($this->phraseKeywordsCount);
        $basePhraseId = array_search($max, $this->phraseKeywordsCount);//var_dump($baseOffset);
        $leftKeywordsCount = 0;
        $leftPhraseIds = [];
        $rightKeywordsCount = 0;
        $rightPhraseIds = [];
        foreach ($this->phraseKeywordsCount as $i => $c) {
            if ($i < $basePhraseId) {
                $leftKeywordsCount += $c;
                $leftPhraseIds[] = $i;
            }
            if ($i > $basePhraseId) {
                $rightKeywordsCount += $c;
                $rightPhraseIds[] = $i;
            }
        }
        $stack = [$basePhraseId];
        if ($rightKeywordsCount >= $leftKeywordsCount) {
            $stack = array_merge($stack, $rightPhraseIds, array_reverse($leftPhraseIds));
        } else {
            $stack = array_merge($stack, array_reverse($leftPhraseIds), $rightPhraseIds);
        }
        $stack = array_merge($stack, $this->phrasesClamped, $this->phraseSidecarOnHead, $this->phraseSidecarOnEnd, $this->phrasesSidecar, $this->phrasesUnclassed);
        $this->timeCost[sprintf('Create list for choosing [ %s ] ', implode('>', $stack))] = timeCost();
        $phrasesPicked = [];
        
        $counter = 0;
        do {
            if (empty($stack)) {
                break;
            }
            $phraseId = array_shift($stack);
            $phrasesPicked[] = $phraseId;
            $counter += $this->phraseCharsCount[$phraseId];
        } while ($counter < $this->getTruncatedCount());
        sort($phrasesPicked);
        $this->timeCost[sprintf('Pick phrases and sort [ %s ] ', implode('-', $phrasesPicked))] = timeCost();
        $snippet = static::parseSequenceToSnippet($phrasesPicked, $this->getTruncatedCount(), $this->phrases, $this->keywords);
        $this->timeCost['Convert to snippet'] = timeCost();
        $snippet['seq'] = implode('-', $phrasesPicked);
        $this->snippets[] = $snippet;
    }
    
    protected function onPhraseEnd($index)
    {
        $phrase = $this->getPhrase($index);
        if ($phrase) {
            $phrase->kwCount = static::getKeywordOccurrence($phrase->getText(), $this->keywords, $highlighted);
            $phrase->highlightedText = $highlighted;
            $phrase->right = $index + 1;
            $phrase->left = $index - 1;
            $this->phraseCharsCount[$index] = $phrase->getCharsCount();
            if ($phrase->kwCount > 0) {
                $this->foundKeyword = true;
                $this->phraseKeywordsCount[$index] = $phrase->kwCount;
            }
            unset($phrase);
        }
    }
}

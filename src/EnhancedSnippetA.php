<?php
namespace Revhub\Snippet;

class EnhancedSnippetA extends Snippet
{
    
    const SENTENCE_ENDINGS = ['.', '!', '?', '。', '！',  '？'];
    
    protected $snippets = [];
    protected $indexPicked;
    
    protected function process()
    {
        $originalText = $this->originalText;
        $occurrenceMax = 0;
        $truncatedCount = $this->getTruncatedCount();
        $index = 0;
        do {
            $snippetObj = new Snippet($originalText, $this->language); // Don't pass keyword to get pure text(no highlighted)
            $snippet = $snippetObj->getSnippet($truncatedCount, false);
            $occurrence = static::getKeywordOccurrence($snippet, $this->keywords, $snippetHighlighted);
            if ($occurrence > $occurrenceMax) {
                $occurrenceMax = $occurrence;
                $this->indexPicked = $index;
            }
            $this->snippets[$index++] = [
                'snippet' => $snippet,
                'snippetHighlighted' => $snippetHighlighted,
                'occurrence' => $occurrence
            ];
            $originalText = static::stripStartingSentence($originalText);
            unset($snippetObj);
        } while ($originalText);
        
        if ($occurrenceMax === 0) {
            $this->indexPicked = 0;
        }
        return $this->snippets[$this->indexPicked]['snippet'];
    }

    protected function highlight()
    {
        return $this->snippets[$this->indexPicked]['snippetHighlighted'];
    }
    
    public function printDebugInfo()
    {
        $this->printDebugInfoCommon();
        echo "<ol>";
        foreach ($this->snippets as $i => $item) {
            echo sprintf(
                '<li class="%s" style="padding:5px;">%s<i style="margin-left:5px;">(length: %d, occurrence: <span style="color:red;">%d</span>)</i></li>',
                $i == $this->indexPicked ? 'row-highlight' : '',
                $item['snippetHighlighted'],
                static::getCharCount($item['snippet']),
                $item['occurrence']
            );
        }
        echo "</ol>";
    }
    
    protected static function stripStartingSentence($text, $endingSigns = null)
    {
        if (!$text) {
            return '';
        }
        $_endingSigns = $endingSigns ? $endingSigns : static::SENTENCE_ENDINGS;
        $i = 0;
        do {
            $char = mb_substr($text, $i, 1);
            if ($char === '') {
                return ''; // The ending signs not found
            }
            $i++;
            if (in_array($char, $_endingSigns)) {
                return ltrim(ltrim(mb_strstr($text, $char), $char));
            }
        } while (true);
    }
}

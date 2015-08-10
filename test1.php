<?php

require __DIR__."/vendor/autoload.php";

$test_text = 'foo {{bar|p1=5|p2=2|99}} baz';

$lexer = new \JMS\Parser\SimpleLexer(
    '/
        # Opening Brace
        (\\{\\{)
        |
        # Closing Brace
        (\\}\\})
        |
        # Parameter Separator
        (\\|)
        |
        (=)

    /x', // The x modifier tells PCRE to ignore whitespace in the regex above.

    // This maps token types to a human readable name.
    array(0 => 'T_UNKNOWN', 1 => 'T_OPEN_BRACE', 2 => 'T_CLOSE_BRACE', 3 => 'T_PARAM_SEPARATOR', 4 => "T_EQUAL"),

    // This function tells the lexer which type a token has. The first element is
    // an integer from the map above, the second element the normalized value.
    function($value) {
        if ('{{' === $value) {
            return array(1, '{{');
        }
        if ('}}' === $value) {
            return array(2, '}}');
        }
        if ('|' === $value) {
            return array(3, '|');
        }
        if ('=' === $value) {
            return array(4, '=');
        }
        return array(0, $value);
    }
);


class DumbParser extends \JMS\Parser\AbstractParser {

  const T_UNKNOWN = 0;
  const T_OPEN_BRACE = 1;
  const T_CLOSE_BRACE = 2;
  const T_PARAM_SEPARATOR = 3;
  const T_EQUAL = 4;

  public function parseInternal()
  {
      $result = [];
      $allTokens = [self::T_UNKNOWN, self::T_OPEN_BRACE, self::T_CLOSE_BRACE, self::T_PARAM_SEPARATOR, self::T_EQUAL];
      while ($this->lexer->isNextAny($allTokens)) {
        $result[] = $this->matchAny($allTokens);
      }
      return $result;
  }

}

class TemplateParser extends DumbParser {

  public function parseInternal()
  {
      $result = [];
      $currentTemplate = "";
      $currentParams = [];
      $allTokens = [self::T_UNKNOWN, self::T_OPEN_BRACE, self::T_CLOSE_BRACE, self::T_PARAM_SEPARATOR, self::T_EQUAL];
      while ($this->lexer->isNextAny($allTokens)) {
        if ($this->lexer->isNext(self::T_UNKNOWN)) {
          if (!$currentTemplate) {
            $this->lexer->moveNext();
            continue;
          }
        }
        if ($this->lexer->isNext(self::T_OPEN_BRACE)) {
          $this->lexer->moveNext();
          $currentTemplate = $this->match(self::T_UNKNOWN);
          continue;
        }
        if ($this->lexer->isNext(self::T_PARAM_SEPARATOR)) {
          $this->lexer->moveNext();
          if ($this->lexer->isNextAny([self::T_PARAM_SEPARATOR, self::T_CLOSE_BRACE])) {
            $currentParams[count($currentParams)] =  "";
            continue;
          }
          $p = $this->match(self::T_UNKNOWN);
          if ($this->lexer->isNext(self::T_EQUAL)) {
            $paramName = $p;
            $this->lexer->moveNext();
            $paramValue = $this->match(self::T_UNKNOWN);
          }
          else {
            $paramName = count($currentParams);
            $paramValue = $p;
          }
          $currentParams[$paramName] = $paramValue;
          continue;
        }
        if ($this->lexer->isNext(self::T_CLOSE_BRACE)) {
          $result[] = ["name" => $currentTemplate, "params" => $currentParams];
          $currentTemplate = "";
          $currentParams = [];
          $this->lexer->moveNext();
          continue;
        }
        error_log("unmatched token ".$this->matchAny($allTokens));
      }
      return $result;
  }

}

#$p = new DumbParser($lexer);
#var_dump($p->parse($test_text));


$p = new TemplateParser($lexer);
print_r($p->parse($test_text));

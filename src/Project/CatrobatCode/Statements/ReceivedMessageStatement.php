<?php

namespace App\Project\CatrobatCode\Statements;

use App\Project\CatrobatCode\SyntaxHighlightingConstants;

class ReceivedMessageStatement extends Statement
{
  /**
   * ReceivedMessageStatement constructor.
   *
   * @param mixed $statementFactory
   * @param mixed $xmlTree
   * @param mixed $spaces
   * @param mixed $value
   */
  public function __construct($statementFactory, $xmlTree, $spaces, $value)
  {
    $value = SyntaxHighlightingConstants::VALUE.$value.SyntaxHighlightingConstants::END;
    parent::__construct($statementFactory, $xmlTree, $spaces,
      $value,
      '');
  }
}
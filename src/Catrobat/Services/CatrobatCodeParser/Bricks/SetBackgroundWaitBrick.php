<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

/**
 * Class SetBackgroundWaitBrick
 * @package App\Catrobat\Services\CatrobatCodeParser\Bricks
 */
class SetBackgroundWaitBrick extends Brick
{
  /**
   * @var
   */
  private $look_file_name;

  /**
   *
   */
  protected function create()
  {
    $this->type = Constants::SET_BACKGROUND_WAIT_BRICK;
    $this->caption = "Set background and wait";

    if ($this->brick_xml_properties->look[Constants::REFERENCE_ATTRIBUTE] !== null)
    {
      $this->look_file_name = $this->brick_xml_properties->look
        ->xpath($this->brick_xml_properties->look[Constants::REFERENCE_ATTRIBUTE])[0]['fileName'];
    }
    else
    {
      $this->look_file_name = $this->brick_xml_properties->look['fileName'];
    }

    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }

  /**
   * @return mixed
   */
  public function getLookFileName()
  {
    return $this->look_file_name;
  }
}
<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;

class JumpingSumoAnimationBrick extends Brick
{
  protected function create(): void
  {
    $this->type = Constants::JUMP_SUMO_ANIMATIONS_BRICK;
    $this->caption = 'Animation Jumping Sumo';
    $this->setImgFile(Constants::JUMPING_SUMO_BRICK_IMG);
  }
}
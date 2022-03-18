<?php

declare(strict_types=1);

namespace broki\trashcanplus\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\sound\Sound;

class RandomOrbSound implements Sound {

    public function encode(Vector3 $pos): array {
        return [LevelEventPacket::create(LevelEvent::SOUND_ORB, 0, $pos)];
    }
}
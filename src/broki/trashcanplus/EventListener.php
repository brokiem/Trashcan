<?php

declare(strict_types=1);

namespace broki\trashcanplus;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\TextFormat;

class EventListener implements Listener {

    public function onInteract(PlayerInteractEvent $event): void {
        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            if ($event->getItem()->getNamedTag()->getInt("trashcan_item", 0)) {
                $event->getPlayer()->sendMessage("[Trashcan]" . TextFormat::GREEN . " Trashcan successfully spawned!");
                $event->getPlayer()->getInventory()->setItemInHand(VanillaBlocks::AIR()->asItem());
                Trashcan::getInstance()->spawnTrashcan(Location::fromObject($event->getBlock()->getPosition()->add(0.5, 0.8, 0.5), $event->getBlock()->getPosition()->getWorld()), null);
                $event->cancel();
            }
        }
    }
}
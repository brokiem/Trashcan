<?php

declare(strict_types=1);

namespace broki\trashcanplus;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat;

class EventListener implements Listener {

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        if ($player->hasPermission("trashcanplus.notify") and !empty(Trashcan::getInstance()->getCachedUpdate())) {
            [$latestVersion, $updateDate, $updateUrl] = Trashcan::getInstance()->getCachedUpdate();

            if (Trashcan::getInstance()->getDescription()->getVersion() !== $latestVersion) {
                $player->sendMessage(" \n§aTrashcanPlus §bv$latestVersion §ahas been released on §b$updateDate. §aDownload the new update at §b$updateUrl\n ");
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event): void {
        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            if ($event->getItem()->getNamedTag()->getInt("trashcan_item", 0)) {
                $event->getPlayer()->sendMessage("[Trashcan]" . TextFormat::GREEN . " Trashcan successfully spawned!");
                $event->getPlayer()->getInventory()->setItemInHand(VanillaBlocks::AIR()->asItem());
                Trashcan::getInstance()->spawnTrashcan(Location::fromObject($event->getBlock()->getPosition()->add(0.5, 0.8, 0.5), $event->getBlock()->getPosition()->getWorld()), ownerXuid: $event->getPlayer()->getXuid());
                $event->cancel();
            }
        }
    }
}
<?php

declare(strict_types=1);

namespace broki\trashcan3d\entity;

use broki\trashcan3d\Trashcan;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class TrashcanEntity extends Human {

    private bool $isOpened;

    public function __construct(Location $location, Skin $skin, ?CompoundTag $nbt = null) {
        parent::__construct($location, $skin, $nbt);

        $this->setCanSaveWithChunk(true);
        $this->isOpened = str_contains($this->getSkin()->getGeometryName(), "open");
    }

    public function isOpened(): bool {
        return $this->isOpened;
    }

    public function attack(EntityDamageEvent $source): void {
        if ($source instanceof EntityDamageByEntityEvent) {
            $attacker = $source->getDamager();

            if ($attacker instanceof Player) {
                $attackerUuid = $attacker->getUniqueId()->toString();

                if (in_array($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)) {
                    $attacker->sendMessage("[Trashcan]" . TextFormat::GREEN . " Despawn trashcan successfully");
                    $this->flagForDespawn();

                    unset(Trashcan::getInstance()->listWhoWannaDespawnTrashcan[array_search($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)]);
                } else if ($attacker->isSneaking()) {
                    if ($this->isOpened) {
                        $this->setSkin(Trashcan::getInstance()->processSkin());
                    } else {
                        $this->setSkin(Trashcan::getInstance()->processSkin(true));
                    }
                    $this->sendSkin();
                    $this->isOpened = str_contains($this->getSkin()->getGeometryName(), "open");
                } else {
                    Trashcan::getInstance()->sendTrashcanInv($attacker);
                }
            }
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool {
        $attackerUuid = $player->getUniqueId()->toString();

        if (in_array($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)) {
            $player->sendMessage("[Trashcan]" . TextFormat::GREEN . " Despawn trashcan successfully");
            $this->flagForDespawn();

            unset(Trashcan::getInstance()->listWhoWannaDespawnTrashcan[array_search($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)]);
        } else if ($player->isSneaking()) {
            if ($this->isOpened) {
                $this->setSkin(Trashcan::getInstance()->processSkin());
            } else {
                $this->setSkin(Trashcan::getInstance()->processSkin(true));
            }
            $this->sendSkin();
            $this->isOpened = str_contains($this->getSkin()->getGeometryName(), "open");
        } else {
            Trashcan::getInstance()->sendTrashcanInv($player);
        }

        return false;
    }
}
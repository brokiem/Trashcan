<?php

declare(strict_types=1);

namespace broki\trashcanplus\entity;

use broki\trashcanplus\Trashcan;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use Ramsey\Uuid\Uuid;

class TrashcanEntity extends Human {

    private bool $isOpened;
    private InvMenu $invMenu;

    public function __construct(Location $location, Skin $skin, ?CompoundTag $nbt = null) {
        parent::__construct($location, $skin, $nbt);

        $this->setCanSaveWithChunk(true);
        $this->isOpened = str_contains($this->getSkin()->getGeometryName(), "open");
    }

    public function isOpened(): bool {
        return $this->isOpened;
    }

    public function getInvMenu(): InvMenu {
        return $this->invMenu;
    }

    public function attack(EntityDamageEvent $source): void {
        if ($source instanceof EntityDamageByEntityEvent) {
            $attacker = $source->getDamager();

            if ($attacker instanceof Player) {
                $attackerUuid = $attacker->getUniqueId()->toString();

                if (in_array($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)) {
                    $attacker->sendMessage("[Trashcan]" . TextFormat::GREEN . " Despawn trashcan successfully");

                    for ($i = 0; $i < 54; $i++) {
                        if (in_array($i, Trashcan::getInstance()->getInventoryBorderSlots(), true)) {
                            continue;
                        }

                        $this->getWorld()->dropItem($this->getPosition(), $this->getInvMenu()->getInventory()->getItem($i));
                    }

                    $trashcanItem = ItemFactory::getInstance()->get(ItemIds::CAULDRON)->setNamedTag(CompoundTag::create()->setInt("trashcan_item", 1)->setString("id", Uuid::uuid4()->toString()));
                    $this->getWorld()->dropItem($this->getPosition(), $trashcanItem->setCustomName(TextFormat::RESET . TextFormat::WHITE . "Trashcan"));

                    $this->flagForDespawn();

                    unset(Trashcan::getInstance()->listWhoWannaDespawnTrashcan[array_search($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)]);
                } else if ($attacker->isSneaking()) {
                    $this->setSkin(Trashcan::getInstance()->processSkin(!$this->isOpened));
                    $this->sendSkin();
                } else {
                    Trashcan::getInstance()->sendTrashcanInv($this->getInvMenu(), $attacker);
                }
            }
        }
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool {
        $attackerUuid = $player->getUniqueId()->toString();

        if (in_array($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)) {
            $player->sendMessage("[Trashcan]" . TextFormat::GREEN . " Despawn trashcan successfully");

            for ($i = 0; $i < 54; $i++) {
                if (in_array($i, Trashcan::getInstance()->getInventoryBorderSlots(), true)) {
                    continue;
                }

                $this->getWorld()->dropItem($this->getPosition(), $this->getInvMenu()->getInventory()->getItem($i));
            }

            $this->flagForDespawn();

            unset(Trashcan::getInstance()->listWhoWannaDespawnTrashcan[array_search($attackerUuid, Trashcan::getInstance()->listWhoWannaDespawnTrashcan, true)]);
        } else if ($player->isSneaking()) {
            $this->setSkin(Trashcan::getInstance()->processSkin(!$this->isOpened));
            $this->sendSkin();
        } else {
            Trashcan::getInstance()->sendTrashcanInv($this->getInvMenu(), $player);
        }

        return false;
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $invMenu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);

        $items = $nbt->getListTag("trashcan_inventory");
        if ($items !== null) {
            /** @var CompoundTag $item */
            foreach ($items as $item) {
                $invMenu->getInventory()->setItem($item->getByte("Slot"), Item::nbtDeserialize($item));
            }
        }

        foreach (Trashcan::getInstance()->getInventoryBorderSlots() as $borderSlot) {
            $namedtag = CompoundTag::create()->setInt("trashcan_border_item", 1);
            $borderItem = VanillaBlocks::IRON_BARS()->asItem()->setNamedTag($namedtag)->setCustomName(" ");
            $invMenu->getInventory()->setItem($borderSlot, $borderItem);
        }

        $clearItem = VanillaBlocks::BARRIER()->asItem()->setNamedTag(CompoundTag::create()->setInt("trashcan_clear_item", 1));
        $invMenu->getInventory()->setItem(53, $clearItem->setCustomName(TextFormat::RESET . TextFormat::RED . "CLEAR TRASH-CAN"));

        $this->invMenu = $invMenu;
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();

        /** @var CompoundTag[] $items */
        $items = [];

        $slotCount = $this->getInvMenu()->getInventory()->getSize();
        for ($slot = 0; $slot < $slotCount; ++$slot) {
            $item = $this->getInvMenu()->getInventory()->getItem($slot);
            if (!$item->isNull()) {
                $items[] = $item->nbtSerialize($slot);
            }
        }

        $nbt->setTag("trashcan_inventory", new ListTag($items, NBT::TAG_Compound));
        return $nbt;
    }

    public function setSkin(Skin $skin): void {
        parent::setSkin($skin);
        $this->isOpened = str_contains($this->getSkin()->getGeometryName(), "open");
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        if ($this->isOpened() and Trashcan::getInstance()->getConfig()->get("enable-throw-item-to-trash", true)) {
            $bb = $this->getBoundingBox()->expandedCopy(0.4, 0.7, 0.4);
            $entities = $this->getWorld()->getNearbyEntities($bb);

            foreach ($entities as $entity) {
                if ($entity instanceof ItemEntity) {
                    $entity->flagForDespawn();
                    $this->getInvMenu()->getInventory()->addItem($entity->getItem());
                }
            }
        }

        return parent::entityBaseTick($tickDiff);
    }
}
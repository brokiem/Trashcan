<?php

declare(strict_types=1);

namespace broki\trashcanplus;

use broki\trashcanplus\command\TrashcanCommand;
use broki\trashcanplus\entity\TrashcanEntity;
use broki\trashcanplus\sound\RandomOrbSound;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\BarrelCloseSound;
use pocketmine\world\sound\BarrelOpenSound;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;

class Trashcan extends PluginBase {
    use SingletonTrait;

    public array $listWhoWannaDespawnTrashcan = [];

    private array $inventoryBorderSlots = [
        0, 1, 2, 6, 7, 8, 24, 25,
        9, 18, 27, 36, 45, 28, 29,
        46, 47, 48, 50, 51, 52, 53,
        17, 26, 35, 44, 49, 33, 34,
        10, 11, 15, 16, 19, 20,
        37, 38, 39, 40, 41, 42, 43
    ];

    protected function onEnable(): void {
        self::setInstance($this);

        $this->checkResources();

        $this->getServer()->getCommandMap()->register("trashcan", new TrashcanCommand("trashcan", "trashcan command"));
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

        EntityFactory::getInstance()->register(TrashcanEntity::class, function(World $world, CompoundTag $nbt): TrashcanEntity {
            return new TrashcanEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['TrashcanEntity']);

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
    }

    public function checkResources(): void {
        $modelPath = $this->getDataFolder() . "model";

        if (!file_exists($modelPath)) {
            @mkdir($modelPath);
        }

        $this->saveResource("model/trashcan.png");
        $this->saveResource("model/trashcan.json");
        $this->saveResource("model/trashcan_open.json");
    }

    public function processSkin(bool $opened = false): Skin {
        $jsonPath = $this->getDataFolder() . "model/trashcan" . ($opened ? "_open" : "") . ".json";
        $texturePath = $this->getDataFolder() . "model/trashcan.png";

        $size = getimagesize($texturePath);
        $img = @imagecreatefrompng($texturePath);

        $bytes = "";
        for ($y = 0; $y < $size[1]; $y++) {
            for ($x = 0; $x < $size[0]; $x++) {
                $colorat = @imagecolorat($img, $x, $y);
                $a = ((~((int)($colorat >> 24))) << 1) & 0xff;
                $r = ($colorat >> 16) & 0xff;
                $g = ($colorat >> 8) & 0xff;
                $b = $colorat & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        @imagedestroy($img);

        $jsonContents = file_get_contents($jsonPath);
        $uuid = Uuid::uuid4()->toString();
        return new Skin($uuid, $bytes, "", "geometry.trashcan" . ($opened ? ".open" : ""), $jsonContents);
    }

    public function spawnTrashcan(Location $location, ?string $nametag = null, ?string $ownerXuid = null): void {
        $locationMod = Location::fromObject($location->add(0, 0.5, 0), $location->getWorld());

        $human = new TrashcanEntity($locationMod, $this->processSkin());
        $human->setOwner($ownerXuid)->spawnToAll();

        if ($nametag !== null) {
            $human->setNameTag($nametag);
        }
    }

    public function sendTrashcanInv(InvMenu $menu, Player $player, bool $withSound = true): void {
        if ($withSound) {
            $player->getWorld()->addSound($player->getPosition()->add(0.5, 0.5, 0.5), new BarrelOpenSound(), [$player]);
        }

        $menu->setName($this->getConfig()->get("trashcan-inv-name", "Trashcan"));
        $menu->setListener(function(InvMenuTransaction $transaction) use ($menu): InvMenuTransactionResult {
            if ($transaction->getItemClicked()->getNamedTag()->getInt("trashcan_confirm_clear_item", 0)) {
                for ($i = 0; $i < 54; $i++) {
                    if (in_array($i, $this->getInventoryBorderSlots(), true)) {
                        continue;
                    }

                    $menu->getInventory()->clear($i);
                }

                $player = $transaction->getPlayer();
                $player->getWorld()->addSound($player->getPosition(), new RandomOrbSound(), [$player]);
                $player->sendMessage("[Trashcan]" . TextFormat::RED . " Your trashcan has been cleaned");

                $clearItem = VanillaBlocks::BARRIER()->asItem()->setNamedTag(CompoundTag::create()->setInt("trashcan_clear_item", 1));
                $menu->getInventory()->setItem(53, $clearItem->setCustomName(TextFormat::RESET . TextFormat::RED . "CLEAR TRASH-CAN"));
                return $transaction->discard();
            }

            if ($transaction->getItemClicked()->getNamedTag()->getInt("trashcan_clear_item", 0)) {
                $confirmClearItem = VanillaItems::NETHER_STAR()->setNamedTag(CompoundTag::create()->setInt("trashcan_confirm_clear_item", 1));
                $menu->getInventory()->setItem(53, $confirmClearItem->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Confirm Clear"));
                return $transaction->discard();
            }

            if ($transaction->getItemClicked()->getNamedTag()->getInt("trashcan_border_item", 0)) {
                return $transaction->discard();
            }

            return $transaction->continue();
        });

        $menu->setInventoryCloseListener(function(Player $player, Inventory $inventory) use ($withSound): void {
            $clearItem = VanillaBlocks::BARRIER()->asItem()->setNamedTag(CompoundTag::create()->setInt("trashcan_clear_item", 1));
            $inventory->setItem(53, $clearItem->setCustomName(TextFormat::RESET . TextFormat::RED . "CLEAR TRASH-CAN"));

            if ($withSound) {
                $player->getWorld()->addSound($player->getPosition()->add(0.5, 0.5, 0.5), new BarrelCloseSound(), [$player]);
            }
        });

        $menu->send($player);
    }

    public function getInventoryBorderSlots(): array {
        return $this->inventoryBorderSlots;
    }
}
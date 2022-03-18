<?php

declare(strict_types=1);

namespace broki\trashcan\command;

use broki\trashcan\Trashcan;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use pocketmine\utils\TextFormat;

class TrashcanCommand extends Command implements PluginOwned {

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!($sender instanceof Player)) {
            return false;
        }

        if (isset($args[0])) {
            switch (strtolower($args[0])) {
                case "spawn":
                case "create":
                    if (!$sender->hasPermission("trashcan.spawn")) {
                        return true;
                    }

                    $sender->sendMessage("[Trashcan]" . TextFormat::GREEN . " Trashcan successfully spawned!");
                Trashcan::getInstance()->spawnTrashcan($sender->getLocation(), $args[1] ?? null);
                    break;
                case "despawn":
                case "remove":
                    if (!$sender->hasPermission("trashcan.despawn")) {
                        return true;
                    }

                    $sender->sendMessage("[Trashcan]" . TextFormat::RED . " Tap the trashcan you want to despawn");
                    Trashcan::getInstance()->listWhoWannaDespawnTrashcan[] = $sender->getUniqueId()->toString();
                    break;
            }
        }

        return false;
    }

    public function getOwningPlugin(): Plugin {
        return Trashcan::getInstance();
    }
}
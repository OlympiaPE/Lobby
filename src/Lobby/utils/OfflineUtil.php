<?php

namespace Lobby\utils;

use InvalidArgumentException;
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;

class OfflineUtil
{
    /**
     * Retrieves the inventory and armor of an offline player.
     *
     * @param string $target The player's name.
     * @return array An array containing the inventory and armor.
     * @throws InvalidArgumentException If the player data cannot be found.
     */
    public static function getInventory(string $target): array
    {
        $nbt = Server::getInstance()->getOfflinePlayerData(strtolower($target)) ??
            throw new InvalidArgumentException("Could not find player data of \"" . strtolower($target) . "\"");

        $inventoryContents = [];
        $armorInventoryContents = [];
        self::readInventoryAndArmorInventory($nbt, $inventoryContents, $armorInventoryContents);

        return ["inventory" => $inventoryContents, "armor" => $armorInventoryContents];
    }

    /**
     * Reads inventory and armor data from an NBT tag.
     *
     * @param CompoundTag $data The player's NBT data.
     * @param array $inventory An array to store the inventory.
     * @param array $armor_inventory An array to store the armor.
     */
    public static function readInventoryAndArmorInventory(CompoundTag $data, array &$inventory, array &$armor_inventory): void
    {
        $inventory = [];
        $armor_inventory = [];
        $tag = $data->getListTag("Inventory");

        if ($tag === null) return;

        /** @var CompoundTag $item */
        foreach ($tag->getIterator() as $item) {
            $slot = $item->getByte("Slot");
            if ($slot >= 0 && $slot < 9) {
                // Do nothing for hotbar slots
            } elseif ($slot >= 100 && $slot < 104) {
                $armor_inventory[$slot - 100] = Item::nbtDeserialize($item);
            } else {
                $inventory[$slot - 9] = Item::nbtDeserialize($item);
            }
        }
    }

    /**
     * Writes inventory contents to an NBT tag.
     *
     * @param CompoundTag $tag The player's NBT data.
     * @param array $inventory An array containing inventory items.
     * @param array $armor_inventory An array containing armor items.
     */
    private static function writeInventoryContents(CompoundTag $tag, array $inventory, array $armor_inventory): void
    {
        $serialized_inventory = [];
        foreach ($inventory as $slot => $item) {
            $serialized_inventory[] = $item->nbtSerialize($slot + 9);
        }
        foreach ($armor_inventory as $slot => $item) {
            $serialized_inventory[] = $item->nbtSerialize($slot + 100);
        }
        $tag->setTag("Inventory", new ListTag($serialized_inventory, NBT::TAG_Compound));
    }

    /**
     * Saves the inventory and armor of an offline player.
     *
     * @param string $target The player's name.
     * @param array $inventoryContents The inventory contents.
     * @param array $armorInventoryContents The armor contents.
     * @throws InvalidArgumentException If the player data cannot be found.
     */
    public static function saveInventory(string $target, array $inventoryContents, array $armorInventoryContents): void
    {
        $nbt = Server::getInstance()->getOfflinePlayerData(strtolower($target)) ??
            throw new InvalidArgumentException("Could not find player data of \"" . strtolower($target) . "\"");

        self::writeInventoryContents($nbt, $inventoryContents, $armorInventoryContents);
        Server::getInstance()->saveOfflinePlayerData(strtolower($target), $nbt);
    }

    /**
     * Retrieves the EnderChest inventory of an offline player.
     *
     * @param string $target The player's name.
     * @return array An array containing the EnderChest inventory.
     * @throws InvalidArgumentException If the player data cannot be found.
     */
    public static function getEnderInventory(string $target): array
    {
        $nbt = Server::getInstance()->getOfflinePlayerData(strtolower($target)) ??
            throw new InvalidArgumentException("Could not find player data of \"" . strtolower($target) . "\"");

        return self::readEnderInventory($nbt);
    }

    /**
     * Reads EnderChest inventory data from an NBT tag.
     *
     * @param CompoundTag $tag The player's NBT data.
     * @return array An array containing the EnderChest inventory.
     */
    public static function readEnderInventory(CompoundTag $tag): array
    {
        $enderChestInventoryTag = $tag->getListTag("EnderChestInventory");
        if ($enderChestInventoryTag === null) return [];

        $ender_inventory = [];
        /** @var CompoundTag $item */
        foreach ($enderChestInventoryTag->getIterator() as $item) {
            $ender_inventory[$item->getByte("Slot")] = Item::nbtDeserialize($item);
        }
        return $ender_inventory;
    }

    /**
     * Writes EnderChest inventory contents to an NBT tag.
     *
     * @param CompoundTag $data The player's NBT data.
     * @param array $inventory An array containing EnderChest items.
     */
    private static function writeEnderInventory(CompoundTag $data, array $inventory): void
    {
        $tag = new ListTag([], NBT::TAG_Compound);
        foreach ($inventory as $slot => $item) {
            $tag->push($item->nbtSerialize($slot));
        }
        $data->setTag("EnderChestInventory", $tag);
    }

    /**
     * Saves the EnderChest inventory of an offline player.
     *
     * @param string $target The player's name.
     * @param array $contents The EnderChest contents.
     * @throws InvalidArgumentException If the player data cannot be found.
     */
    public static function saveEnderInventory(string $target, array $contents): void
    {
        $nbt = Server::getInstance()->getOfflinePlayerData(strtolower($target)) ??
            throw new InvalidArgumentException("Could not find player data of \"" . strtolower($target) . "\"");

        self::writeEnderInventory($nbt, $contents);
        Server::getInstance()->saveOfflinePlayerData(strtolower($target), $nbt);
    }
}
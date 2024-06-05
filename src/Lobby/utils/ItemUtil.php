<?php

namespace Lobby\utils;

use Lobby\utils\reflection\ReflectionUtils;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionException;

class ItemUtil
{
    /**
     * @param Item $item
     * @return array
     */
    public static function jsonSerialize(Item $item): array{
        $itemData["nbt_b64"] = base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize())));
        $itemData["format"] = "nbt";
        return $itemData;
    }

    /**
     * @param array $data
     * @return Item
     */
    public static function legacyStringJsonDeserialize(array $data): Item{
        $data = base64_decode($data["nbt_b64"]);
        $item = (new LittleEndianNbtSerializer())->read($data);
        return Item::nbtDeserialize($item->mustGetCompoundTag());
    }

    /**
     * Allows you to clone an item without deleting the original one
     * So this turns into two totally different items on pocketmine, but on the customer side it's exactly the same.
     *
     * @param Item $item
     * @param string $itemStringId
     * @param string $cloneIdentifier
     * @return void
     * @throws ReflectionException
     */
    public static function clone(Item $item, string $itemStringId, string $cloneIdentifier): void
    {
        $itemTypeDictionary = TypeConverter::getInstance()->getItemTypeDictionary();
        $currentItemId = $itemTypeDictionary->fromStringId($itemStringId);

        $value = ReflectionUtils::getProperty(ItemTypeDictionary::class, $itemTypeDictionary, "intToStringIdMap");
        ReflectionUtils::setProperty(ItemTypeDictionary::class, $itemTypeDictionary, "intToStringIdMap", $value + [$currentItemId => $cloneIdentifier]);
        $value = ReflectionUtils::getProperty(ItemTypeDictionary::class, $itemTypeDictionary, "stringToIntMap");
        ReflectionUtils::setProperty(ItemTypeDictionary::class, $itemTypeDictionary, "stringToIntMap", $value + [$cloneIdentifier => $currentItemId]);
        StringToItemParser::getInstance()->register($cloneIdentifier, fn() => clone $item);
        GlobalItemDataHandlers::getDeserializer()->map($cloneIdentifier, fn() => clone $item);
        GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($cloneIdentifier));
        CreativeInventory::getInstance()->add(clone $item);
    }
}
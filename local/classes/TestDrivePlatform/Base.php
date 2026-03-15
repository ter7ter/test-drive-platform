<?php

namespace TestDrivePlatform;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\SystemException;

class Base
{
    /**
     * Загружает DataClass блоков
     * @param array $blocks
     * @return array
     * @throws SystemException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     */
    protected static function loadBlocks(array $blocks): array
    {
        $result = [];
        foreach ($blocks as $block) {
            $blockTable = HighloadBlockTable::getList([
                'filter' => ['=TABLE_NAME' => $block]
            ])->fetch();
            if (!$blockTable) {
                throw new SystemException("HighloadBlock '{$block}' not found.");
            }
            $result[] = HighloadBlockTable::compileEntity($blockTable)->getDataClass();
        }
        return $result;
    }
}
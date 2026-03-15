<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;

class TestDrivesListComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        try {
            $this->arResult['ITEMS'] = $this->getTestDrives();
        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
        
        $this->includeComponentTemplate();
    }

    protected function getTestDrives()
    {
        if (!Loader::includeModule("highloadblock")) {
            throw new \Exception('Модуль highloadblock не установлен');
        }

        //Получаем highload-блоки
        $blockDrives = HighloadBlockTable::getList(['filter' => ['=NAME' => 'TestDrives']])->fetch();
        $blockCars = HighloadBlockTable::getList(['filter' => ['=NAME' => 'Cars']])->fetch();

        if (!$blockDrives) {
            throw new \Exception('Highload-блок "TestDrives" не найден');
        }
        if (!$blockCars) {
            throw new \Exception('Highload-блок "Cars" не найден');
        }

        $drivesDataClass = HighloadBlockTable::compileEntity($blockDrives)->getDataClass();
        $carsDataClass = HighloadBlockTable::compileEntity($blockCars)->getDataClass();

        //Формируем запрос
        $result = $drivesDataClass::getList([
            'select' => [
                'ID',
                'DATE_START' => 'UF_DATE_START',
                'DATE_END' => 'UF_DATE_END',
                'CAR_MODEL' => 'CAR.UF_MODEL'
            ],
            'runtime' => [
                new Reference(
                    'CAR',
                    $carsDataClass,
                    Join::on('this.UF_CAR', 'ref.ID'),
                    ['join_type' => 'inner']
                )
            ],
            'order' => [
                'ID' => 'DESC'
            ]
        ]);
        return $result->fetchAll();
    }
}

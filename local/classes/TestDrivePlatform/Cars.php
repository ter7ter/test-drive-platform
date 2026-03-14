<?php
namespace TestDrivePlatform;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Throwable;

Loader::includeModule("highloadblock");

class Cars
{
    public int $id;
    public string $model;
    public int $year;
    public string $vin;
    public string $statusCode;
    public string $price_per_day;

    /**
     * Конструктор, получает авто по id
     * @param int $id
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function __construct(int $id)
    {
        //Получаем блок Cars
        $blockCars = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Cars']
        ])->fetch();
        if (!$blockCars) {
            throw new SystemException("HighloadBlock 'Cars' not found.");
        }
        $entityCars = HighloadBlockTable::compileEntity($blockCars);
        $entityCarsDataClass = $entityCars->getDataClass();

        //Получаем данные
        $result = $entityCarsDataClass::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1
        ]);
        $carData = $result->fetch();
        if (!$carData) {
            throw new SystemException("Car id {$id} not found.");
        }
        $this->id = $id;
        foreach (['model', 'year', 'vin', 'price_per_day'] as $fieldName) {
            $this->$fieldName = $carData['UF_'.strtoupper($fieldName)];
        }

        //Получаем статус
        $blockStatuses = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Statuses']
        ])->fetch();
        if (!$blockStatuses) {
            throw new SystemException("HighloadBlock 'Statuses' not found.");
        }
        $statusEntity = HighloadBlockTable::compileEntity($blockStatuses);
        $statusDataClass = $statusEntity->getDataClass();
        $statusItem = $statusDataClass::getList([
            'filter' => ['=ID' => $carData['UF_STATUS']],
            'select' => ['UF_CODE'],
            'limit' => 1
        ])->fetch();
        if (!$statusItem) {
            throw new SystemException("Status {$carData['UF_STATUS']} not found in 'Statuses'.");
        }
        $this->statusCode = $statusItem['UF_CODE'];
    }

    /**
     * Создание одного автомобиля
     *
     * @param string $model
     * @param int $year
     * @param string $vin
     * @param string $statusCode
     * @param int $price_per_day
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function create(
        string $model,
        int    $year,
        string $vin,
        string $statusCode,
        int    $price_per_day
    ): void
    {
        //Получаем статус
        $blockStatuses = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Statuses']
        ])->fetch();
        if (!$blockStatuses) {
            throw new SystemException("HighloadBlock 'Statuses' not found.");
        }

        $statusEntity = HighloadBlockTable::compileEntity($blockStatuses);
        $statusDataClass = $statusEntity->getDataClass();
        $statusItem = $statusDataClass::getList([
            'filter' => ['=UF_CODE' => $statusCode],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();

        if ($statusItem) {
            $statusId = $statusItem['ID'];
        } else {
            throw new SystemException("Status with code '{$statusCode}' not found");
        }

        //Получаем блок Cars
        $blockCars = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Cars']
        ])->fetch();
        if (!$blockCars) {
            throw new SystemException("HighloadBlock 'Cars' not found.");
        }
        $entityCars = HighloadBlockTable::compileEntity($blockCars);
        $entityCarsDataClass = $entityCars->getDataClass();

        // 3. Prepare fields for the new Car element
        $elementFields = array(
            'UF_MODEL' => $model,
            'UF_YEAR' => $year,
            'UF_VIN' => $vin,
            'UF_STATUS' => $statusId,
            'UF_PRICE_PER_DAY' => $price_per_day,
        );

        //Добавляем элемент в Cars
        $result = $entityCarsDataClass::add($elementFields);
        if (!$result->isSuccess()) {
            throw new SystemException(implode(', ', $result->getErrorMessages()));
        }
    }

    public static function createMany($data) {
        //Получаем блок статусов
        $blockStatuses = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Statuses']
        ])->fetch();
        if (!$blockStatuses) {
            throw new SystemException("HighloadBlock 'Statuses' not found.");
        }
        $statusEntity = HighloadBlockTable::compileEntity($blockStatuses);
        $statusDataClass = $statusEntity->getDataClass();

        //Получаем id статусов для всех машин и формируем данные для добавления
        $statusIds = [];
        $addCarsData = [];
        foreach ($data as $item) {
            if (!isset($statusIds[$item['statusCode']])) {
                $statusItem = $statusDataClass::getList([
                    'filter' => ['=UF_CODE' => $item['statusCode']],
                    'select' => ['ID'],
                    'limit' => 1
                ])->fetch();
                if ($statusItem) {
                    $statusIds[$item['statusCode']] = $statusItem['ID'];
                } else {
                    throw new SystemException("Status with code '{$item['stausCode']}' not found");
                }
            }
            $addCarsData[] = [
                'UF_MODEL' => $item['model'],
                'UF_YEAR' => $item['year'],
                'UF_VIN' => $item['vin'],
                'UF_STATUS' => $statusIds[$item['statusCode']],
                'UF_PRICE_PER_DAY' => $item['price_per_day'],
            ];
        }

        //Получаем блок Cars
        $blockCars = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Cars']
        ])->fetch();
        if (!$blockCars) {
            throw new SystemException("HighloadBlock 'Cars' not found.");
        }
        $entityCars = HighloadBlockTable::compileEntity($blockCars);
        $entityCarsDataClass = $entityCars->getDataClass();

        $db = Application::getConnection();
        try {
            $db->startTransaction();
            $result = $entityCarsDataClass::addMulti($addCarsData);
            if (!$result->isSuccess()) {
                throw new SystemException(implode(', ', $result->getErrorMessages()));
            }
            $db->commitTransaction();
        } catch (Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    public function update()
    {
        //Получаем id статуса
        $blockStatuses = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Statuses']
        ])->fetch();
        if (!$blockStatuses) {
            throw new SystemException("HighloadBlock 'Statuses' not found.");
        }
        $statusEntity = HighloadBlockTable::compileEntity($blockStatuses);
        $statusDataClass = $statusEntity->getDataClass();
        $statusItem = $statusDataClass::getList([
            'filter' => ['=UF_CODE' => $this->statusCode],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();
        if ($statusItem) {
            $statusId = $statusItem['ID'];
        } else {
            throw new SystemException("Status with code '{$item['stausCode']}' not found");
        }

        //Получаем блок Cars
        $blockCars = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'Cars']
        ])->fetch();
        if (!$blockCars) {
            throw new SystemException("HighloadBlock 'Cars' not found.");
        }
        $entityCars = HighloadBlockTable::compileEntity($blockCars);
        $entityCarsDataClass = $entityCars->getDataClass();
        $result = $entityCarsDataClass::update(
            $this->id,
            [
                'UF_STATUS' => $statusId,
                'UF_PRICE_PER_DAY' => $this->price_per_day,
            ]
        );
        if (!$result->isSuccess()) {
            throw new SystemException(implode(', ', $result->getErrorMessages()));
        }
    }

}
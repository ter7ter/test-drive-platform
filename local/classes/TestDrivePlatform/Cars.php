<?php
namespace TestDrivePlatform;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Throwable;

Loader::includeModule("highloadblock");

class Cars extends Base
{
    public int $id;
    public string $model;
    public int $year;
    public string $vin;
    public string $status_code;
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
        //Загружаем блоки
        [$carsDataClass, $statusesDataClass] = static::loadBlocks(['Cars', 'Statuses']);

        //Получаем данные
        $result = $carsDataClass::getList([
            'select' => [
                '*',
                'UF_STATUS_CODE' => 'STATUS_REF.UF_CODE'
            ],
            'filter' => ['=ID' => $id],
            'limit' => 1,
            'runtime' => [
                new Reference(
                    'STATUS_REF',
                    $statusesDataClass,
                    Join::on('this.UF_STATUS', 'ref.ID'),
                    ['join_type' => 'left']
                )
            ]
        ]);
        $carData = $result->fetch();
        if (!$carData) {
            throw new SystemException("Car id {$id} not found.");
        }
        $this->id = $id;
        foreach (['model', 'year', 'vin', 'price_per_day', 'status_code'] as $fieldName) {
            $this->$fieldName = $carData['UF_'.strtoupper($fieldName)];
        }
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
        //Загружаем блоки
        [$carsDataClass, $statusesDataClass] = static::loadBlocks(['Cars', 'Statuses']);

        //Получаем ID статуса
        $statusItem = $statusesDataClass::getList([
            'filter' => ['=UF_CODE' => $statusCode],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();

        if ($statusItem) {
            $statusId = $statusItem['ID'];
        } else {
            throw new SystemException("Status with code '{$statusCode}' not found");
        }

        //Добавляем элемент в Cars
        $result = $carsDataClass::add([
            'UF_MODEL' => $model,
            'UF_YEAR' => $year,
            'UF_VIN' => $vin,
            'UF_STATUS' => $statusId,
            'UF_PRICE_PER_DAY' => $price_per_day,
        ]);
        if (!$result->isSuccess()) {
            throw new SystemException(implode(', ', $result->getErrorMessages()));
        }
    }

    /**
     * Создание сразу нескольких авто
     *
     * @param $data
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Throwable
     * @throws \Bitrix\Main\DB\SqlQueryException
     */
    public static function createMany(array $data): void
    {
        //Загружаем блоки
        [$carsDataClass, $statusesDataClass] = static::loadBlocks(['Cars', 'Statuses']);

        //Получаем id статусов для всех машин и формируем данные для добавления
        $statusIds = [];
        $addCarsData = [];
        foreach ($data as $item) {
            if (!isset($statusIds[$item['statusCode']])) {
                $statusItem = $statusesDataClass::getList([
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

        $db = Application::getConnection();
        try {
            $db->startTransaction();
            $result = $carsDataClass::addMulti($addCarsData);
            if (!$result->isSuccess()) {
                throw new SystemException(implode(', ', $result->getErrorMessages()));
            }
            $db->commitTransaction();
        } catch (Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Редактирование информации об авто
     * обновляет поля UF_STATUS и UF_PRICE_PER_DAY
     *
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function update(): void
    {
        //Получаем блоки
        [$carsDataClass, $statusesDataClass] = static::loadBlocks(['Cars', 'Statuses']);

        //Получем статус
        $statusItem = $statusesDataClass::getList([
            'filter' => ['=UF_CODE' => $this->status_code],
            'select' => ['ID'],
            'limit' => 1
        ])->fetch();
        if ($statusItem) {
            $statusId = $statusItem['ID'];
        } else {
            throw new SystemException("Status with code '{$this->status_code}' not found");
        }

        //Обновляем информацию
        $result = $carsDataClass::update(
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

    /**
     * Удаляет автомобиль и его бронирования, с проверкой будущих бронирований
     *
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Throwable
     * @throws \Bitrix\Main\DB\SqlQueryException
     */
    public function delete(): void
    {
        //Получаем блоки
        [$carsDataClass, $testDrivesDataClass] = static::loadBlocks(['Cars', 'test_drives']);

        //Проверяем бронирования авто
        $result = $testDrivesDataClass::getList([
                'select' => ['ID'],
                'filter' => [
                    '=UF_CAR' => $this->id,
                    '>UF_DATE_END' => new DateTime()
                ]
            ]);
        if ($result->fetch()) {
            throw new SystemException("Невозможно удалить этот автомобиль - на него есть незавершённые бронирования.");
        }
        $db = Application::getConnection();
        $db->startTransaction();
        try {
            //Сначала удаляем бронирования
            $result = $testDrivesDataClass::getList([
                'select' => ['ID'],
                'filter' => [
                    '=UF_CAR' => $this->id,
                ]
            ]);
            while ($testDrive = $result->fetch()) {
                $testDrivesDataClass::delete($testDrive['ID']);
            }

            //Удаляем само авто
            $carsDataClass::delete($this->id);
            $db->commitTransaction();
        } catch (Throwable $e) {
            $db->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Получает список автомобилей
     *
     * @param $statusCode
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getList($statusCode = null): array
    {
        //Получаем блоки
        [$carsDataClass, $statusesDataClass] = static::loadBlocks(['Cars', 'Statuses']);

        //Получаем список автомобилей
        $filter = [];
        if ($statusCode) {
            $filter = [
                '=STATUS_REF.UF_CODE' => $statusCode
            ];
        }
        $result = $carsDataClass::getList([
            'select' => [
                '*',
                'UF_STATUS_CODE' => 'STATUS_REF.UF_CODE'
            ],
            'filter' => $filter,
            'runtime' => [
                new Reference(
                    'STATUS_REF',
                    $statusesDataClass,
                    Join::on('this.UF_STATUS', 'ref.ID'),
                    ['join_type' => 'left']
                )
            ]
        ]);
        //Заполняем данные для результата
        $cars = [];
        while ($item = $result->fetch()) {
            $car['id'] = $item['ID'];
            foreach (['model', 'year', 'vin', 'status', 'status_code'] as $fieldName) {
                $car[$fieldName] = $item['UF_'.strtoupper($fieldName)];
            }
            $cars[] = $car;
        }
        return $cars;
    }
}
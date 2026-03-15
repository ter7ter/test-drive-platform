<?php

namespace TestDrivePlatform;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;

Loader::includeModule("highloadblock");

class TestDrives extends Base
{
    /**
     * Создание нового бронирования авто на тест-драйв
     *
     * @param Cars $car
     * @param $dateStart
     * @param $dateEnd
     * @return void
     * @throws SystemException
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     */
    public static function create(Cars $car, string $dateStart, string $dateEnd): void
    {
        //Проверяем не в ремонте ли
        if ($car->status_code == 'repair') {
            throw new SystemException("Этот авто в ремонте");
        }

        //Получаем блок бронирований
        [$testDrivesDataClass] = static::loadBlocks(['TestDrives']);

        //Проверяем не забронировано ли уже авто на эти даты
        try {
            $startDateTime = new DateTime($dateStart, 'Y-m-d H:i:s');
            $endDateTime = new DateTime($dateEnd, 'Y-m-d H:i:s');
        } catch (SystemException $e) {
            throw new SystemException("Неверный формат даты");
        }
        $result = $testDrivesDataClass::getList([
                'select' => ['UF_DATE_START', 'UF_DATE_END'],
                'filter' =>
                    [
                        '=UF_CAR' => $car->id,
                        '<=UF_DATE_START' => $startDateTime,
                        '>=UF_DATE_END' => $endDateTime,
                    ]
            ]);
        if ($data = $result->fetch()) {
            $errorMessage = "Этот авто уже забронирован в этом интервале. Занятые даты:\n";
            while ($data) {
                $errorMessage .= "c ". $data['UF_DATE_START']->toString() . " по " . $data['UF_DATE_END']->toString() ."\n";
                $data = $result->fetch();
            }
            throw new SystemException($errorMessage);
        }

        //Расчитываем количество дней(включая неполные)
        $diffInSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
        $daysCount = ceil($diffInSeconds / (24 * 60 * 60));
        //Расчитываем стоимость
        $totalCost = $car->price_per_day*$daysCount;

        //Создаём бронирование
        $result = $testDrivesDataClass::add([
                'UF_CAR' => $car->id,
                'UF_DATE_START' => $startDateTime,
                'UF_DATE_END' => $endDateTime,
                'UF_TOTAL_COST' => $totalCost
            ]);
        if (!$result->isSuccess()) {
            throw new SystemException(implode(', ', $result->getErrorMessages()));
        }
    }
}
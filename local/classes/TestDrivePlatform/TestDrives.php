<?php

namespace TestDrivePlatform;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Protobuf\Exception;

class TestDrives
{
    public static function create(Cars $car, $dateStart, $dateEnd) {
        //Проверяем не в ремонте ли
        if ($car->statusCode == 'repair') {
            throw new SystemException("Этот авто в ремонте");
        }

        //Получаем блок TestDrives
        $blockTestDrives = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => 'test_drives']
        ])->fetch();
        if (!$blockTestDrives) {
            throw new SystemException("HighloadBlock 'TestDrives' not found.");
        }
        $entityTestDrivesClass = HighloadBlockTable::compileEntity($blockTestDrives)->getDataClass();

        //Проверяем не забронировано ли уже авто на эти даты
        try {
            $startDateTime = new DateTime($dateStart);
            $endDateTime = new DateTime($dateEnd);
        } catch (SystemException $e) {
            throw new SystemException("Неверный формат даты");
        }
        $result = $entityTestDrivesClass::getList(
            [
                'select' => ['UF_DATE_START', 'UF_DATE_END'],
                'filter' =>
                    [
                        '=UF_CAR' => $car->id,
                        '<=UF_DATE_START' => $startDateTime,
                        '>=UF_DATE_END' => $endDateTime,
                    ]
            ]
        );
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
        $result = $entityTestDrivesClass::add(
            [
                'UF_CAR' => $car->id,
                'UF_DATE_START' => $startDateTime,
                'UF_DATE_END' => $endDateTime,
                'UF_TOTAL_COST' => $totalCost
            ]
        );
        if (!$result->isSuccess()) {
            throw new SystemException(implode(', ', $result->getErrorMessages()));
        }
    }
}
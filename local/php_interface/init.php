<?php
use Bitrix\Main\EventManager;
use Bitrix\Main\ORM\EntityError;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Highloadblock\HighloadBlockTable;

Bitrix\Main\Loader::registerNamespace(
    'TestDrivePlatform',
    $_SERVER["DOCUMENT_ROOT"].'/local/classes/TestDrivePlatform/',
);

EventManager::getInstance()->addEventHandler(
    '',
    CarsUniqueFieldBlockValidator::HL_BLOCK_NAME.'OnBeforeAdd',
    ['CarsUniqueFieldBlockValidator', 'checkFields']
);

EventManager::getInstance()->addEventHandler(
    '',
    CarsUniqueFieldBlockValidator::HL_BLOCK_NAME.'OnBeforeUpdate',
    ['CarsUniqueFieldBlockValidator', 'checkFields']
);
/**
 * Класс для проверки уникальности полей при сохранении и добавлении блока
 */
class CarsUniqueFieldBlockValidator
{
    const UNIQUE_FIELDS = ['UF_VIN'];

    const HL_BLOCK_NAME = 'Cars';

    /**
     * Проверяет уникальность значения в поле.
     * @param Event $event
     * @return EventResult
     */
    public static function checkFields(Event $event): EventResult
    {
        $result = new EventResult();
        $fields = $event->getParameter("fields");
        $entity = $event->getEntity();

        // Получаем блок
        $block = HighloadBlockTable::getList([
            'filter' => ['=NAME' => static::HL_BLOCK_NAME]
        ])->fetch();

        // Если блок не найден - выходим
        if (!$block) {
            return $result;
        }

        foreach (static::UNIQUE_FIELDS as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                continue;
            }
            $filter = [];
            // При обновлении - исключаем обновляемый из проверки
            if ($event->getEventType() === 'OnBeforeUpdate') {
                $id = $event->getParameter('id');
                $filter['!=ID'] = $id['ID'];
            }
            $uniqueValue = $fields[$fieldName];
            $filter = ['=' . $fieldName => $uniqueValue];

            //Ищем дубликат
            $hlEntity = HighloadBlockTable::compileEntity($block);
            $hlDataClass = $hlEntity->getDataClass();
            $existingItem = $hlDataClass::getList([
                'select' => ['ID'],
                'filter' => $filter,
                'limit'  => 1
            ])->fetch();

            // Если дубликат найден, добавляем ошибку
            if ($existingItem) {
                $fieldTitle = $entity->getField($fieldName)->getTitle();
                $result->addError(new EntityError(
                    "Элемент со значением {$uniqueValue} в поле «{$fieldTitle}» уже существует."
                ));
            }
        }

        return $result;
    }
}

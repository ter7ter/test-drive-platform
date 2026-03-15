<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
//$APPLICATION->SetTitle("TestDrive Platform");

$APPLICATION->IncludeComponent(
    "testdriveplatform:testdrives.list",
    "",
    [],
    false
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

if (!CModule::IncludeModule('iblock')) {
    return;
}

// Получаем список всех инфоблоков
$arIBlocks = [];
$rsIBlock = CIBlock::GetList(
    ['SORT' => 'ASC'],
    ['ACTIVE' => 'Y']
);
while ($arr = $rsIBlock->Fetch()) {
    $arIBlocks[$arr['ID']] = '[' . $arr['ID'] . '] ' . $arr['NAME'];
}


$arComponentParameters = [
    "GROUPS" => [
        "IBLOCKS" => [
            "NAME" => "Настройки инфоблоков",
            "SORT" => 100
        ],
        "DATA" => [
            "NAME" => "Настройки данных",
            "SORT" => 200
        ]
    ],

    "PARAMETERS" => [
        "CARS_IBLOCK_ID" => [
            "PARENT" => "IBLOCKS",
            "NAME" => "ID инфоблока автомобилей",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "N",
            "MULTIPLE" => "N",
            "ADDITIONAL_VALUES" => "N"
        ],

        "CATEGORIES_IBLOCK_ID" => [
            "PARENT" => "IBLOCKS",
            "NAME" => "ID инфоблока категорий комфорта",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "N",
            "MULTIPLE" => "N",
            "ADDITIONAL_VALUES" => "N"
        ],

        "DRIVERS_IBLOCK_ID" => [
            "PARENT" => "IBLOCKS",
            "NAME" => "ID инфоблока водителей",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "N",
            "MULTIPLE" => "N",
            "ADDITIONAL_VALUES" => "N"
        ],

        "POSITIONS_IBLOCK_ID" => [
            "PARENT" => "IBLOCKS",
            "NAME" => "ID инфоблока должностей",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "N",
            "MULTIPLE" => "N",
            "ADDITIONAL_VALUES" => "N"
        ],

        "HL_BLOCK_NAME" => [
            "PARENT" => "DATA",
            "NAME" => "Название HL-блока бронирований",
            "TYPE" => "STRING",
            "DEFAULT" => "Bookings"
        ],
    ]
];
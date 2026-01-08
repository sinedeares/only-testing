<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Доступные служебные автомобили",
    "DESCRIPTION" => "Выводит список доступных служебных автомобилей на запрошенное время с учетом должности пользователя",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Пользовательские компоненты",
        "CHILD" => [
            "ID" => "car_booking",
            "NAME" => "Бронирование автомобилей"
        ]
    ],
];
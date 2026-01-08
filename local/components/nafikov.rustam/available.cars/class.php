<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\UserTable;
use Bitrix\Highloadblock\HighloadBlockTable;

class AvailableCars extends CBitrixComponent
{
    private $userId;
    private $userPositionId;

    protected function checkModules()
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не установлен');
        }

        if (!Loader::includeModule('highloadblock')) {
            throw new \Exception('Модуль highloadblock не установлен');
        }

        return true;
    }

    public function onPrepareComponentParams($arParams)
    {
        $arParams['CARS_IBLOCK_ID'] = (int)($arParams['CARS_IBLOCK_ID'] ?? 0);
        $arParams['CATEGORIES_IBLOCK_ID'] = (int)($arParams['CATEGORIES_IBLOCK_ID'] ?? 0);
        $arParams['DRIVERS_IBLOCK_ID'] = (int)($arParams['DRIVERS_IBLOCK_ID'] ?? 0);
        $arParams['POSITIONS_IBLOCK_ID'] = (int)($arParams['POSITIONS_IBLOCK_ID'] ?? 0);

        // Проверка названия HL-блока
        $arParams['HL_BLOCK_NAME'] = trim($arParams['HL_BLOCK_NAME'] ?? 'Bookings');

        if ($arParams['CARS_IBLOCK_ID'] <= 0) {
            throw new \Exception('Не указан ID инфоблока автомобилей');
        }

        if ($arParams['CATEGORIES_IBLOCK_ID'] <= 0) {
            throw new \Exception('Не указан ID инфоблока категорий');
        }

        if ($arParams['DRIVERS_IBLOCK_ID'] <= 0) {
            throw new \Exception('Не указан ID инфоблока водителей');
        }

        if ($arParams['POSITIONS_IBLOCK_ID'] <= 0) {
            throw new \Exception('Не указан ID инфоблока должностей');
        }

        if (empty($arParams['HL_BLOCK_NAME'])) {
            throw new \Exception('Не указано название HL-блока бронирований');
        }

        return $arParams;
    }

    //получение id пользователя и id его должности
    private function getCurrentUserData()
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            throw new \Exception('Пользователь не авторизован');
        }

        $this->userId = $USER->GetID();

        $userResult = UserTable::getList([
            'filter' => [
                '=ID' => $this->userId
            ],
            'select' => [
                'ID', 'UF_POSITION'
            ],
        ])->fetch();

        if (!$userResult || !$userResult['UF_POSITION']) {
            throw new \Exception('У пользователя не указана должность');
        }

        $this->userPositionId = (int)$userResult['UF_POSITION'];

    }

    //получение доступных категорий комфорта для должности пользователя
    private function getAvailableCategories()
    {
        $categories = [];

        $position = ElementTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $this->arParams['POSITIONS_IBLOCK_ID'],
                '=ID' => $this->userPositionId,
                'ACTIVE' => 'Y',
            ],
            'select' => [
                'ID'
            ],
        ])->fetch();

        if (!$position) {
            // Пробуем найти должность без проверки активности для диагностики
            $positionCheck = ElementTable::getList([
                'filter' => [
                    '=IBLOCK_ID' => $this->arParams['POSITIONS_IBLOCK_ID'],
                    '=ID' => $this->userPositionId,
                ],
                'select' => ['ID', 'ACTIVE', 'NAME']
            ])->fetch();

            if (!$positionCheck) {
                throw new \Exception('Должность с ID ' . $this->userPositionId . ' не найдена в инфоблоке ' . $this->arParams['POSITIONS_IBLOCK_ID']);
            } elseif ($positionCheck['ACTIVE'] !== 'Y') {
                throw new \Exception('Должность "' . $positionCheck['NAME'] . '" (ID: ' . $this->userPositionId . ') неактивна');
            } else {
                throw new \Exception('Должность не найдена по неизвестной причине');
            }
        }

        $dbProps = \CIBlockElement::GetProperty(
            $this->arParams['POSITIONS_IBLOCK_ID'],
            $this->userPositionId,
            ['sort' => 'asc'],
            ['CODE' => 'AVAILABLE_CATEGORIES']
        );

        while ($prop = $dbProps->Fetch()) {
            if (!empty($prop['VALUE'])) {
                $categories[] = (int)$prop['VALUE'];
            }
        }

        return array_unique($categories);

    }

    //получение занятых автомобилей за искомый период
    private function getBookedCarIds($dateFrom, $dateTo)
    {
        $bookedCars = [];

        try {
            $hlBlock = HighloadBlockTable::getList([
                'filter' => ['=NAME' => $this->arParams['HL_BLOCK_NAME']],
                'select' => ['ID', 'NAME', 'TABLE_NAME'],

            ])->fetch();

            if (!$hlBlock) {
                return $bookedCars;
            }

            $entity = HighloadBlockTable::compileEntity($hlBlock);
            $entityDataClass = $entity->getDataClass();

            $result = $entityDataClass::getList([
                'filter' => [
                    'LOGIC' => 'OR',
                    [
                        '<=UF_DATE_FROM' => $dateFrom,
                        '>=UF_DATE_TO' => $dateFrom
                    ],
                    [
                        '<=UF_DATE_FROM' => $dateTo,
                        '>=UF_DATE_TO' => $dateTo
                    ],
                    [
                        '>=UF_DATE_FROM' => $dateFrom,
                        '<=UF_DATE_TO' => $dateTo
                    ]
                ],
                'select' => [
                    'UF_CAR_ID'
                ],
            ]);
            while ($booking = $result->fetch()) {
                $bookedCars[] = (int)$booking['UF_CAR_ID'];
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return array_unique($bookedCars);
    }

    //получение данных водителей для вывода
    private function getDriversData($driverIds)
    {
        $driversData = [];

        $driverIds = array_filter(array_unique($driverIds));

        if (empty($driverIds)) {
            return $driversData;
        }

        $drivers = ElementTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $this->arParams['DRIVERS_IBLOCK_ID'],
                '=ID' => $driverIds,
                'ACTIVE' => 'Y',
            ],
            'select' => [
                'ID', 'NAME',
            ],
        ]);

        while ($driver = $drivers->fetch()) {
            $driversData[$driver['ID']] = [
                'ID' => $driver['ID'],
                'NAME' => $driver['NAME'],
                'PHONE' => null
            ];
        }

        $phoneProps = \CIBlockElement::GetPropertyValues(
            $this->arParams['DRIVERS_IBLOCK_ID'],
            ['ID' => $driverIds],
            false,
            ['CODE' => 'PHONE']
        );

        foreach ($phoneProps as $driverId => $props) {
            if (isset($driversData[$driverId]) && !empty($props['PHONE'])) {
                $driversData[$driverId]['PHONE'] = $props['PHONE'];
            }
        }

        return $driversData;
    }

    //получение названий категорий для отображения в результатах
    private function getCategoryNamesForDisplay($categoryIds)
    {
        $categoriesNames = [];

        $categoryIds = array_filter(array_unique($categoryIds));

        if (empty($categoryIds)) {
            return $categoriesNames;
        }

        $categories = ElementTable::getList([
            'filter' => [
                '=IBLOCK_ID' => $this->arParams['CATEGORIES_IBLOCK_ID'],
                '=ID' => $categoryIds,
                '=ACTIVE' => 'Y'
            ],
            'select' => ['ID', 'NAME']
        ]);

        while ($category = $categories->fetch()) {
            $categoriesNames[$category['ID']] = $category['NAME'];
        };

        return $categoriesNames;
    }

    private function getAvailableCars($categories, $bookedCarIds)
    {
        $cars = [];

        $filter = [
            'IBLOCK_ID' => $this->arParams['CARS_IBLOCK_ID'],
            'ACTIVE' => 'Y',
        ];
        
        if (!empty($bookedCarIds)) {
            $filter['!ID'] = $bookedCarIds;
        }

        $result = ElementTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'NAME', 'IBLOCK_ID'],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
        ]);

        $carsRaw = [];

        while ($car = $result->fetch()) {
            $carsRaw[$car['ID']] = [
                'ID' => $car['ID'],
                'NAME' => $car['NAME'],
                'CATEGORY_ID' => null,
                'DRIVER_ID' => null,
                'LICENSE_PLATE' => null,
                'VIN' => null,
                'BRAND' => null,
                'MODEL' => null
            ];
        }

        if (empty($carsRaw)) {
            return [];
        }

        $carIds = array_keys($carsRaw);

        $neededPropertyCodes = ['CATEGORY', 'DRIVER', 'LICENSE_PLATE', 'VIN', 'BRAND', 'MODEL'];

        foreach ($carIds as $carId) {
            $dbProps = \CIBlockElement::GetProperty(
                $this->arParams['CARS_IBLOCK_ID'],
                $carId,
                ['sort' => 'asc']
            );

            while ($prop = $dbProps->Fetch()) {
                if (!in_array($prop['CODE'], $neededPropertyCodes)) {
                    continue;
                }

                switch ($prop['CODE']) {
                    case 'CATEGORY':
                        $carsRaw[$carId]['CATEGORY_ID'] = (int)$prop['VALUE'];
                        break;
                    case 'DRIVER':
                        $carsRaw[$carId]['DRIVER_ID'] = (int)$prop['VALUE'];
                        break;
                    case 'LICENSE_PLATE':
                        $carsRaw[$carId]['LICENSE_PLATE'] = $prop['VALUE'];
                        break;
                    case 'VIN':
                        $carsRaw[$carId]['VIN'] = $prop['VALUE'];
                        break;
                    case 'BRAND':
                        $carsRaw[$carId]['BRAND'] = $prop['VALUE'];
                        break;
                    case 'MODEL':
                        $carsRaw[$carId]['MODEL'] = $prop['VALUE'];
                        break;
                }
            }
        }

        $categoryIds = [];
        $driverIds = [];

        foreach ($carsRaw as $carData) {
            // Проверяем доступность категории
            if (!in_array($carData['CATEGORY_ID'], $categories)) {
                continue;
            }

            $categoryIds[] = $carData['CATEGORY_ID'];
            $driverIds[] = $carData['DRIVER_ID'];
            $cars[] = $carData;
        }

        $categoriesNames = $this->getCategoryNamesForDisplay($categoryIds);
        $driversData = $this->getDriversData($driverIds);

        foreach ($cars as &$car) {
            $car['CATEGORY_NAME'] = $categoriesNames[$car['CATEGORY_ID']] ?? null;

            if (isset($driversData[$car['DRIVER_ID']])) {
                $car['DRIVER_NAME'] = $driversData[$car['DRIVER_ID']]['NAME'];
                $car['DRIVER_PHONE'] = $driversData[$car['DRIVER_ID']]['PHONE'];
            } else {
                $car['DRIVER_NAME'] = null;
                $car['DRIVER_PHONE'] = null;
            }
        }
        unset($car);

        return $cars;
    }

    private function validateDates()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        $dateFromStr = $request->get('date_from');
        $dateToStr = $request->get('date_to');

        if (!$dateFromStr || !$dateToStr) {
            throw new \Exception('Не указаны параметры date_from и date_to');
        }

        $dateFromStr = urldecode($dateFromStr);
        $dateToStr = urldecode($dateToStr);

        $dateFromStr = str_replace('T', ' ', trim($dateFromStr));
        $dateToStr = str_replace('T', ' ', trim($dateToStr));

        try {
            $phpDateFrom = \DateTime::createFromFormat('Y-m-d H:i:s', $dateFromStr);
            $phpDateTo = \DateTime::createFromFormat('Y-m-d H:i:s', $dateToStr);

            if ($phpDateFrom === false || $phpDateTo === false) {
                throw new \Exception('Не удалось распарсить дату. Проверьте формат: YYYY-MM-DD HH:MM:SS');
            }

            $dateFrom = DateTime::createFromTimestamp($phpDateFrom->getTimestamp());
            $dateTo = DateTime::createFromTimestamp($phpDateTo->getTimestamp());

        } catch (\Exception $e) {
            throw new \Exception('Неверный формат даты. Используйте: YYYY-MM-DD HH:MM:SS');
        }

        if ($dateTo->getTimestamp() <= $dateFrom->getTimestamp()) {
            throw new \Exception('Дата окончания бронирования должна быть позже даты начала');
        }

        $now = new DateTime();
        if ($dateFrom->getTimestamp() < $now->getTimestamp()) {
            throw new \Exception('Дата начала бронирования не должна быть в прошлом');
        }

        return [
            'DATE_FROM' => $dateFrom,
            'DATE_TO' => $dateTo
        ];

    }

    public function executeComponent()
    {
        try {
            $this->checkModules();
            $this->getCurrentUserData();
            $dates = $this->validateDates();

            // формируем ключ кеша
            $cacheId = md5(serialize([
                'USER_ID' => $this->userId,
                'USER_POSITION_ID' => $this->userPositionId,
                'DATE_FROM' => $dates['DATE_FROM']->format('Y-m-d H:i:s'),
                'DATE_TO' => $dates['DATE_TO']->format('Y-m-d H:i:s'),
            ]));

            $cacheDir = '/car_booking_component';
            $cacheTime = 3600;

            //создаём тэгированный кэш, чтобы кэш очищался при изменении HL Bookings
            // (добавление записи, освобождение брони и тд)
            //очистка при изменении через clearByTag('car_booking_list')
            $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cache = \Bitrix\Main\Data\Cache::createInstance();

            if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
                $cachedData = $cache->getVars();

                // Восстанавливаем объекты DateTime из строк
                $cachedData['DATE_FROM'] = new \DateTime($cachedData['DATE_FROM_STRING']);
                $cachedData['DATE_TO'] = new \DateTime($cachedData['DATE_TO_STRING']);
                unset($cachedData['DATE_FROM_STRING'], $cachedData['DATE_TO_STRING']);

                $this->arResult = $cachedData;

            } elseif ($cache->startDataCache()) {
                $taggedCache->startTagCache($cacheDir);
                $taggedCache->registerTag('car_booking_list');
                $taggedCache->endTagCache();

                $availableCategories = $this->getAvailableCategories();

                if (empty($availableCategories)) {
                    $cache->abortDataCache();
                    throw new \Exception('Для вашей должности не настроены доступные категории автомобилей');
                }

                $bookedCarIds = $this->getBookedCarIds($dates['DATE_FROM'], $dates['DATE_TO']);
                $availableCars = $this->getAvailableCars($availableCategories, $bookedCarIds);

                $this->arResult = [
                    'CARS' => $availableCars,
                    'DATE_FROM' => $dates['DATE_FROM'],
                    'DATE_TO' => $dates['DATE_TO'],
                    'DATE_FROM_STRING' => $dates['DATE_FROM']->format('Y-m-d H:i:s'),
                    'DATE_TO_STRING' => $dates['DATE_TO']->format('Y-m-d H:i:s'),
                    'DATE_FROM_FORMATTED' => $dates['DATE_FROM']->format('d.m.Y H:i'),
                    'DATE_TO_FORMATTED' => $dates['DATE_TO']->format('d.m.Y H:i'),
                    'USER_ID' => $this->userId,
                    'USER_POSITION_ID' => $this->userPositionId,
                    'AVAILABLE_CATEGORIES' => $availableCategories,
                    'TOTAL_CARS' => count($availableCars),
                    'TOTAL_BOOKED' => count($bookedCarIds)
                ];

                $cache->endDataCache($this->arResult);

            }

            $this->includeComponentTemplate();

        } catch (\Exception $e) {
            $this->arResult = [
                'ERROR' => $e->getMessage(),
                'CARS' => []
            ];

            ShowError($this->arResult['ERROR']);
        }
    }
}
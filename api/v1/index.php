<?php
/**
 * Точка входа для API v1
 */

// Подключаем ядро Битрикс
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Context;
use TestDrivePlatform\Cars;
use TestDrivePlatform\TestDrives;

function routeApi(string $entityType, string $action, string $entityId, string $method): array
{
    $httpCode = 200;
    $request = Context::getCurrent()->getRequest();
    Switch ([$entityType, $action, $method]) {
        case ['cars', 'list', 'GET']:
            // Получение списка автомобилей
            $statusCode = $request->getQuery('status');
            $response = Cars::getList($statusCode);
            break;
        case ['cars', '', 'GET']:
            // Получение конкретного автомобиля по ID
            $car = new Cars((int)$entityId);
            if (is_null($car->id)) {
                $httpCode = 404;
                $response = ['error' => 'Not Found', 'message' => 'Car not found.'];
                break;
            }
            $response = [
                'id' => $car->id,
                'model' => $car->model,
                'year' => $car->year,
                'vin' => $car->vin,
                'status_code' => $car->status_code,
                'price_per_day' => $car->price_per_day
            ];
            break;
        case ['cars', '', 'POST']:
            // Обновление автомобиля
            $carId = (int)$entityId;
            if (empty($carId)) {
                $httpCode = 400;
                $response = ['error' => 'Bad Request', 'message' => 'Car ID is required.'];
                break;
            }
            $car = new Cars($carId);
            if (is_null($car->id)) {
                $httpCode = 404;
                $response = ['error' => 'Not Found', 'message' => 'Car not found.'];
                break;
            }

            $statusCode = $request->getPost('status_code');
            $pricePerDay = $request->getPost('price_per_day');

            if ($statusCode === null && $pricePerDay === null) {
                $httpCode = 422;
                $response = ['error' => 'Unprocessable Entity', 'message' => 'At least one field (status_code or price_per_day) must be provided for update.'];
                break;
            }

            if ($statusCode !== null) {
                $car->status_code = $statusCode;
            }
            if ($pricePerDay !== null) {
                if (!is_numeric($pricePerDay) || $pricePerDay < 0) {
                    $httpCode = 422;
                    $response = ['error' => 'Unprocessable Entity', 'message' => 'price_per_day must be a non-negative number.'];
                    break;
                }
                $car->price_per_day = $pricePerDay;
            }

            $car->update();
            $response = ['success' => true, 'message' => 'Car updated successfully'];
            break;
        case ['cars', 'create', 'POST']:
            // Создание автомобиля
            $model = $request->getPost('model');
            $year = $request->getPost('year');
            $vin = $request->getPost('vin');
            $statusCode = $request->getPost('status_code');
            $pricePerDay = $request->getPost('price_per_day');

            $errors = [];
            if (empty($model)) {
                $errors[] = 'model is required.';
            }
            if (empty($year) || !ctype_digit((string)$year) || (int)$year <= 1900) {
                $errors[] = 'year is required and must be a valid year greater than 1900.';
            }
            if (empty($vin)) {
                $errors[] = 'vin is required.';
            }
            if (empty($statusCode)) {
                $errors[] = 'status_code is required.';
            }
            if (!is_numeric($pricePerDay) || $pricePerDay < 0) {
                $errors[] = 'price_per_day is required and must be a non-negative number.';
            }

            if (!empty($errors)) {
                $httpCode = 422;
                $response = ['error' => 'Unprocessable Entity', 'messages' => $errors];
                break;
            }

            Cars::create($model, (int)$year, $vin, $statusCode, $pricePerDay);
            $httpCode = 201;
            $response = ['success' => true, 'message' => 'Car created successfully'];
            break;
        case ['cars', 'create-many', 'POST']:
            // Массовое создание автомобилей
            $carsData = $request->getPost('cars');
            if (!is_array($carsData)) {
                $httpCode = 400;
                $response = ['error' => 'Invalid data format', 'message' => 'cars must be an array'];
                break;
            }

            $errors = [];
            foreach ($carsData as $index => $carData) {
                $carErrors = [];
                if (empty($carData['model'])) {
                    $carErrors[] = 'model is required.';
                }
                if (empty($carData['year']) || !ctype_digit((string)$carData['year']) || (int)$carData['year'] <= 1900) {
                    $carErrors[] = 'year is required and must be a valid year greater than 1900.';
                }
                if (empty($carData['vin'])) {
                    $carErrors[] = 'vin is required.';
                }
                if (empty($carData['status_code'])) {
                    $carErrors[] = 'status_code is required.';
                }
                if (!isset($carData['price_per_day']) || !is_numeric($carData['price_per_day']) || $carData['price_per_day'] < 0) {
                    $carErrors[] = 'price_per_day is required and must be a non-negative number.';
                }

                if (!empty($carErrors)) {
                    $errors['car_'.($index + 1)] = $carErrors;
                }
            }

            if (!empty($errors)) {
                $httpCode = 422;
                $response = ['error' => 'Unprocessable Entity', 'messages' => $errors];
                break;
            }

            Cars::createMany($carsData);
            $httpCode = 201;
            $response = ['success' => true, 'message' => 'Cars created successfully', 'count' => count($carsData)];
            break;
        case ['cars', '', 'DELETE']:
            // Удаление автомобиля
            $carId = (int)$entityId;
            if (empty($carId)) {
                $httpCode = 400;
                $response = ['error' => 'Bad Request', 'message' => 'Car ID is required.'];
                break;
            }
            $car = new Cars($carId);
            if (is_null($car->id)) {
                $httpCode = 404;
                $response = ['error' => 'Not Found', 'message' => 'Car not found.'];
                break;
            }
            $car->delete();
            $response = ['success' => true, 'message' => 'Car deleted successfully'];
            break;
        case ['testdrives', '', 'POST']:
            // Создание бронирования тест-драйва
            $carId = $request->getPost('car_id');
            $dateStart = $request->getPost('date_start');
            $dateEnd = $request->getPost('date_end');

            $errors = [];
            $car = null;
            if (empty($carId) || !ctype_digit((string)$carId)) {
                $errors[] = 'car_id is required and must be an integer.';
            } else {
                $car = new Cars((int)$carId);
                if (is_null($car->id)) {
                    $httpCode = 404;
                    $response = ['error' => 'Not Found', 'message' => 'Car not found.'];
                    break;
                }
            }

            if (empty($dateStart) || !($d = \DateTime::createFromFormat('Y-m-d H:i:s', $dateStart)) || $d->format('Y-m-d H:i:s') !== $dateStart) {
                $errors[] = 'date_start is required and must be in Y-m-d H:i:s format.';
            }

            if (empty($dateEnd) || !($d = \DateTime::createFromFormat('Y-m-d H:i:s', $dateEnd)) || $d->format('Y-m-d H:i:s') !== $dateEnd) {
                $errors[] = 'date_end is required and must be in Y-m-d H:i:s format.';
            }

            if (!empty($errors)) {
                $httpCode = 422;
                $response = ['error' => 'Unprocessable Entity', 'messages' => $errors];
                break;
            }

            TestDrives::create($car, $dateStart, $dateEnd);
            $response = ['success' => true, 'message' => 'Test drive booked successfully'];
            break;
        default:
            $response = ['error' => 'Unknown entity type'];
            $httpCode = 404;
    }
    return [$httpCode, $response];
}

header('Content-Type: application/json');
try {
    // Получаем HTTP метод и путь
    $request = Context::getCurrent()->getRequest();
    $httpMethod = $request->getRequestMethod();
    $path = trim(parse_url($request->getRequestUri(), PHP_URL_PATH), '/');
    
    // Разбираем путь к API
    $pathParts = explode('/', $path);
    $apiPath = $pathParts[0] ?? '';
    $apiVersion = $pathParts[1] ?? '';
    $entityType = $pathParts[2] ?? '';
    if (isset($pathParts[3]) && is_numeric($pathParts[3])) {
        $entityId = $pathParts[3];
        $action = '';
    } else {
        $action = $pathParts[3] ?? '';
        $entityId = $pathParts[4] ?? '';
    }

    $response = [];
    $httpCode = 200;

    // Проверяем, что это действительно API запрос
    if ($apiPath !== 'api' || $apiVersion !== 'v1') {
        $response = ['error' => 'Invalid API endpoint'];
        $httpCode = 404;
    } else {
        [$httpCode, $response] = routeApi($entityType, $action, $entityId, $httpMethod);
    }

    http_response_code($httpCode);
    // Отправляем ответ
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Обработка ошибок
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php';
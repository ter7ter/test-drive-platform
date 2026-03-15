# API v1 для TestDrivePlatform

## Описание
Этот API предоставляет доступ к функциональности платформы тест-драйвов через REST-интерфейс.

## Базовый URL
`/api/v1/`


## Методы

### Автомобили (cars)

#### GET /api/v1/cars/list
Получить список всех автомобилей.

Параметры:
- `status` (опционально) - фильтр по статусу автомобиля.

Пример:
```
GET /api/v1/cars/list?status=available
```

#### GET /api/v1/cars/{id}
Получить информацию о конкретном автомобиле.

Пример:
```
GET /api/v1/cars/1
```

#### POST /api/v1/cars/create
Создать новый автомобиль.

Параметры (x-www-form-urlencoded):
- `model` - модель автомобиля
- `year` - год выпуска
- `vin` - VIN номер
- `status_code` - код статуса
- `price_per_day` - цена за день

Пример (данные в теле запроса):
```
model=Toyota+Camry&year=2022&vin=1HGBH41JXMN109186&status_code=available&price_per_day=5000
```

#### POST /api/v1/cars/create-many
Массовое создание автомобилей.

Параметры (x-www-form-urlencoded):
- `cars` - массив автомобилей.

Пример (данные в теле запроса):
```
cars[0][model]=Model S&cars[0][year]=2023&...&cars[1][model]=Model 3&...
```

#### POST /api/v1/cars/{id}
Обновить информацию об автомобиле.

Параметры (x-www-form-urlencoded):
- `status_code` (опционально) - код статуса
- `price_per_day` (опционально) - цена за день

Пример (данные в теле запроса для /api/v1/cars/1):
```
status_code=maintenance&price_per_day=6000
```

#### DELETE /api/v1/cars/{id}
Удалить автомобиль.

Пример:
```
DELETE /api/v1/cars/1
```

### Бронирования тест-драйвов (testdrives)

#### POST /api/v1/testdrives
Создать новое бронирование тест-драйва.

Параметры (x-www-form-urlencoded):
- `car_id` - ID автомобиля
- `date_start` - дата начала (в формате `YYYY-MM-DD HH:MM:SS`)
- `date_end` - дата окончания (в формате `YYYY-MM-DD HH:MM:SS`)

Пример (данные в теле запроса):
```
car_id=1&date_start=2026-03-15 10:00:00&date_end=2026-03-15 12:00:00
```

## Ответы и Коды состояния

### Успешные ответы (200 OK)
```json
{
 "success": true,
  "message": "Operation completed successfully"
}
```
Или тело ответа с запрошенными данными.

### Ошибки

- **400 Bad Request**: Некорректный синтаксис запроса (например, неправильная структура данных).
- **404 Not Found**: Запрашиваемый ресурс не найден (например, автомобиль с указанным {id}).
- **422 Unprocessable Entity**: Ошибка валидации данных. В теле ответа будет содержаться подробная информация об ошибках.
  ```json
  {
    "error": "Unprocessable Entity",
    "messages": [
      "vin is required."
    ]
  }
  ```
- **500 Internal Server Error**: Внутренняя ошибка сервера.
  ```json
  {
    "error": "Internal Server Error",
    "message": "Detailed error description"
  }
  ```

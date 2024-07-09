# Импорт базы ГАР

## Требования к системе

PHP не ниже 8.1, утилита wget

## Установка приложения

Клонируйте приложение или скачайте его архивом

```shell
git clone git@github.com:IggorGor/gar.git
```

Перейдите в каталог с приложением и настройте права на каталоги
```shell
cd gar
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data bootstrap/cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

Настройте **.env**: необходимо установить ```APP_ENV=production, APP_DEBUG=false, DB_CONNECTION(=pgsql для PostgreSQL), DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, QUEUE_CONNECTION=database``` и **config/gar.php**. Если ваша база не PostgreSQL, имя базы данных 
обязательно должно быть **gar**.

Установите зависимости

```shell
composer install --no-dev --optimize-autoloader
```

Выполните миграции

```shell
php artisan migrate
```

## Запуск приложения

Выполните команду

```shell
php artisan gar:complete-full-import
```

Дождитесь её выполнения. Время зависит от производительности вашей системы и скорости
Интернета.

Воспользуйтесь представлением **gar.gar_data_by_uuid_61** для доступа к данным

```postgresql
select * from gar.gar_data_by_uuid_61
where city_name = 'Ново-Талицы'
and street_name = '5-я Изумрудная'
```
Или воспользуйтесь моделью **Models\Gar\GarDataByUUID**

```php
$result = GarDataByUUID::where('house_object_guid', '=',
    '5cef293c-745f-4053-bed6-05466f2758f4')->first();
```

## Обновление данных

Выполните команду

```shell
php artisan gar:update
```

## Дополнительные команды

- **gar:full-download** — скачивает полную выгрузку в файловое хранилище
- **gar:full-extract** — извлекает необходимые файлы из полной выгрузки
- **gar:full-import** — ставит задания на парсинг xml полной выгрузки и запись данных в БД
- **gar:dif-extract** — извлекает необходимые файлы из архива обновления
- **gar:dif-import** — ставит задания на парсинг xml обновления и запись данных в БД
- **gar:start-workers** — запускает обработчики очереди Laravel
- **gar:update** — применяет обновления
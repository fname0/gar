# Импорт базы ГАР

## Требования к системе

PHP не ниже 8.1, утилита wget

## Установка приложения

Клонируйте приложение или скачайте его архивом

```shell
git clone git@github.com:fname0/gar.git
```

Перейдите в каталог с приложением и настройте права на каталоги
```shell
cd gar
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data bootstrap/cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

Отредактируйте в **.env** ```DB_CONNECTION(=pgsql для PostgreSQL), DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD```( во избежании утечек памяти и некорректной работы не следует менять ```APP_ENV=production, APP_DEBUG=false, QUEUE_CONNECTION=database``` ) и **config/gar.php**( скорее всего необходимо будет указать только коды интересующих вас регионов в ```region_code``` ). Если ваша база не PostgreSQL, имя базы данных обязательно должно быть **gar**.

Установите зависимости

```shell
composer install --no-dev --optimize-autoloader
```

Выполните миграции( **перед выполнением миграций обязательно указать регионы в config/gar.php** )

```shell
php artisan migrate
```

## Установка полной выгрузки

Выполните команду

```shell
php artisan gar:complete-full-import
```

Готово. Время зависит от производительности вашей системы и скорости
Интернета( при среднестатистических данных архив полной выгрузки скачивается ≈45мин, а один регион устанавливается ≈13мин ).

Воспользуйтесь представлением **gar.gar_data_by_uuid_61** для доступа к данным( для каждого региона своё представление, последние две цифры - код региона )

```postgresql
select * from gar.gar_data_by_uuid_61
where city_name = 'Ново-Талицы'
and street_name = '5-я Изумрудная'
```

## Обновление данных

Выполните команду( ищет все доступные обновления, обычно одно обновление не занимает больше 1-2 минут, обновления выходят, как правило, раз в 3-4 дня )

```shell
php artisan gar:update
```

## Изменение регионов

Для того, чтобы изменить регионы, информация по которым хранится в БД, необходимо сначала, **не меняя список регионов в config/gar.php**, удалить старые миграции( **удалятся все данные из БД, которые были созданы ранее данной программой** )

```shell
php artisan migrate:rollback
```

Затем в **config/gar.php** изменить ```region_code``` на необходимый, после выполнить миграции

```shell
php artisan migrate
```

Готово, можно установить актуальную полную выгрузку( [инструция тут](https://github.com/fname0/gar?tab=readme-ov-file#установка-полной-выгрузки) )

## Дополнительные команды

- **gar:full-download** — скачивает полную выгрузку в файловое хранилище
- **gar:full-extract** — извлекает необходимые файлы из полной выгрузки
- **gar:full-import** — ставит задания на парсинг xml полной выгрузки и запись данных в БД
- **gar:dif-extract** — извлекает необходимые файлы из архива обновления
- **gar:dif-import** — ставит задания на парсинг xml обновления и запись данных в БД
- **gar:start-workers** — запускает обработчики очереди Larave

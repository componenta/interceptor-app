# Componenta Interceptor App

Интеграция сборки для Interceptor 3, App 4, DI 5 и Config 3.

Зарегистрируйте `Componenta\Interceptor\ConfigProvider`, затем `Componenta\Interceptor\App\ConfigProvider`. Composer metadata по-прежнему публикует провайдер для генерируемого списка.

Провайдер добавляет `InterceptorBuilder` в `app.builders`. Сборка запускается обычной командой приложения:

```bash
php bin/console.php app:build
```

`list` и `--help` не создают билдер. Он получает существующий `app.discovery.source` и путь через `PathResolverInterface`, создаёт каталог и атомарно публикует собственный файл через временный файл и rename. Ошибка исходного дискаверинга или записи сохраняет прежний опубликованный файл.

Путь задаётся `Componenta\Interceptor\App\ConfigKey::MAP_FILE` (`interceptors.map_file`), по умолчанию `var/cache/build/interceptors.php`.

## Артефакт и выполнение

В файле хранится обычный массив сигнатур методов и позиций атрибутов:

```php
return [
    'App\\Controller::show' => [0, 2],
    'App\\Controller::plain' => [],
];
```

Карта не хранит экземпляры атрибутов, аргументы их конструкторов и сервисы. Runtime выбирает native `ReflectionAttribute` и вызывает `newInstance()` при каждом выполнении. Сохраняются свежие объектные аргументы, проверки target и повторяемости, исключения, порядок и scope.

Если класс атрибута недоступен во время сборки, метод не добавляется в карту и использует исходные метаданные. Runtime повторно проверяет неполную классификацию, чтобы учитывать позднее подключение автозагрузчика.

Для отсутствующих в карте методов, функций и замыканий используются их native-метаданные. Отсутствующий, повреждённый или недоступный артефакт приводит к fallback на исходные метаданные. Runtime не запускает билдер и не сканирует классы приложения. Код и соответствующий артефакт публикуются вместе: структурная валидность старой карты не проверяет актуальность исходников.

Удалены `InterceptorMapCompiler`, `InterceptorMapContributor` и регистрация через compile contributors. Подключите новый ConfigProvider и пересоберите приложение через `app:build`.

## Проверка

Расположите соседние пакеты в `packages`, затем выполните:

```bash
composer --working-dir=integration install
composer --working-dir=integration test
composer --working-dir=integration test:packages
composer --working-dir=integration analyse
```

Интеграция проверяет настоящий ConfigProvider/DI, консольную сборку, Symfony Serializer, PSR-7 ответы и пагинацию с локальными пакетами.

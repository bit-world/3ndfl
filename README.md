# Как запустить программу?

## Онлайн:

https://t.me/invest_3ndfl_bot

ПРОМОКОД: GIT_3NDFL

## Windows:

Устанавливаем интерпретатор PHP (встроенный веб-сервер) https://windows.php.net/downloads/releases/ любую свежую версию

Скачиваем саму программу https://github.com/bit-world/3ndfl/archive/main.zip

### 1. Открываем консоль по адресу папки 3ndfl (удерживайте **Shift** + правой кнопкой мыши щелкните папку)
![Шаг 1](https://github.com/bit-world/3ndfl/blob/main/info/step1.png)

### 2. В командной строке пишем путь до интерпретатора PHP (не забудьте распаковать zip архив) и добавляем параметры запуска встроенного веб-сервера **-S 127.0.0.1:80**
![Шаг 2](https://github.com/bit-world/3ndfl/blob/main/info/step2.png)

### 3. Если все сделано правильно, консоль будет выглядеть примерно так
![Шаг 3](https://github.com/bit-world/3ndfl/blob/main/info/step3.png)

### 4. Если сервер запустился (шаг 3), то можно запусить программу в браузере по адресу **127.0.0.1**
![Шаг 4](https://github.com/bit-world/3ndfl/blob/main/info/step4.png)

### 5. Если появилась ошибка "Call to undefined function mb_convert_encoding()"
Скопируйте файл php.ini в каталог с интерпретатором PHP и перезапустите сервер (Шаг 2)

### 6. Завершить работу сервера можно командой Ctrl+C или просто закрыть окно консоли


# Генерируем файл декларации

1. Загружаем отчет Тинькофф или заполненный [шаблон Excel](https://github.com/bit-world/3ndfl/blob/main/sample.xlsx?raw=true)

2. Нажимаем кнопку "Проверка", если данные подгрузились верно, можно генерировать файл декларации dc0

3. Загружаем dc0 в [Программу подготовки 3-НДФЛ](https://www.gnivc.ru/software/fnspo/ndfl_3_4/)

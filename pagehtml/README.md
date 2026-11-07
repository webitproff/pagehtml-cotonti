# Page HTML Files (`pagehtml`) для Cotonti

**Version** · Cotonti 1.0.x · PHP 8.0+ · License BSD

Плагин для Cotonti CMF, который даёт полноценное управление файлами в каталоге `datas/html/page/` из админ-панели: создание подпапок, создание / редактирование / удаление файлов `.html`, `.htm`, `.txt`, `.md`. Редактор на CodeMirror 5 с подсветкой синтаксиса, сохранение по AJAX без перезагрузки страницы.

Плагин рассчитан на работу вместе с безопасной функцией `cot_readraw()` модуля Page (директива `include:` в тексте страницы).

**Важно:** правки в модуле Page (функция `cot_readraw` и её вызов) нужны **в первую очередь из соображений безопасности**. Удобный редактор файлов — следствие, а не причина. Без безопасного чтения `include:` старый код остаётся уязвимым к path traversal; плагин лишь упрощает работу с уже защищённым механизмом.

---

## 📑 Оглавление

1. [Overview](#1-overview)
   - 1.1. Что это
   - 1.2. Ключевые возможности
   - 1.3. Системные требования
   - 1.4. Совместимость
   - 1.5. Ссылки
2. [Безопасность и правки модуля Page](#2-безопасность-и-правки-модуля-page)
   - 2.1. Зачем менять `cot_readraw`
   - 2.2. Полный код безопасной `cot_readraw`
   - 2.3. Вызов в `page.main.php`
   - 2.4. Что именно защищает новая реализация
3. [Как это работает](#3-как-это-работает)
   - 3.1. Архитектура
   - 3.2. Структура плагина
   - 3.3. Хуки
   - 3.4. Список файлов и навигация
   - 3.5. Редактор
   - 3.6. Параметры URL
   - 3.7. Безопасность плагина
4. [Пошаговая установка](#4-пошаговая-установка)
5. [Пошаговое использование](#5-пошаговое-использование)
6. [Конфигурация плагина](#6-конфигурация-плагина)
7. [Руководство по безопасности](#7-руководство-по-безопасности)
8. [Troubleshooting](#8-troubleshooting)
9. [FAQ](#9-faq)
10. [Обновление и удаление](#10-обновление-и-удаление)
11. [Roadmap](#11-roadmap)
12. [Лицензия](#12-лицензия)

---

## 1. Overview

### 1.1. Что это

**Page HTML Files** — плагин администрирования для Cotonti. Он позволяет работать с файлами, которые подключаются к страницам модуля Page через директиву:

```text
include:category_name/page-101.html
```

Файлы физически лежат в:

```text
datas/html/page/
```

Внутри можно создавать подпапки (например `legal/`, `docs/`, `blocks/`) и хранить в них HTML, текст или Markdown.

Плагин **не заменяет** модуль Page и **не пишет** в базу данных контент страниц. Он только управляет файлами на диске. Чтение файла при показе страницы посетителю выполняет `cot_readraw()` в модуле Page.

### 1.2. Ключевые возможности

- Единый список файлов и папок в `datas/html/page/` с навигацией по подпапкам.
- Создание подпапок.
- Создание, редактирование и удаление файлов `.html`, `.htm`, `.txt`, `.md`.
- Редактор CodeMirror 5 (тема eclipse, line numbers, match/autoClose brackets).
- Сохранение по AJAX (`fetch` + CSRF-токен) без перезагрузки.
- Скачивание текущей копии файла.
- Удаление только **пустых** папок.
- Жёсткая привязка всех операций к каталогу `datas/html/page/` (`realpath` + проверка префикса).
- Двуязычность: `ru` / `en`.
- Без правок ядра Cotonti: только хуки `tools` и `ajax`.

### 1.3. Системные требования

| Компонент | Минимум | Рекомендуется |
|-----------|---------|---------------|
| PHP | 8.0 | 8.5+ |
| MySQL | 8.0 | 8.4+ |
| MariaDB | 10.2 | 10.6+ |
| Cotonti | 1.0.x | 1.0.x |

Расширения PHP: `pdo_mysql`, `mbstring`, `json` (встроено с PHP 8.0).

Права на файлы: каталог `datas/html/page/` и файлы внутри должны быть доступны веб-серверу на чтение; для создания/сохранения/удаления — на запись (типично `0664` / `0775`).

Браузер: современный с поддержкой `fetch`, `URLSearchParams`, `ResizeObserver`.

### 1.4. Совместимость

Проверялось / рассчитано на:

- Cotonti 1.0.x
- PHP 8.0–8.5
- MySQL 8.x / MariaDB 10.x
- Apache 2.4, Nginx + php-fpm

Плагин использует API Cotonti 1.0 (`Cot::$cfg`, `Cot::$sys`, `Cot::$usr`). Для Siena 0.9.x не предназначен.

### 1.5. Ссылки

- Репозиторий: [github.com/webitproff/pagehtml-cotonti](https://github.com/webitproff/pagehtml-cotonti)
- Cotonti: [github.com/Cotonti/Cotonti](https://github.com/Cotonti/Cotonti)
- CodeMirror 5: [codemirror.net/5](https://codemirror.net/5/)

---

## 2. Безопасность и правки модуля Page

### 2.1. Зачем менять `cot_readraw`

Историческая реализация в Cotonti сводилась к проверке «нет подстроки `..`» и вызову `file_get_contents`. Этого **недостаточно**:

- нет жёсткой привязки к каталогу `datas/html/page/`;
- нет `realpath` и проверки, что итоговый путь остаётся внутри разрешённой директории;
- нет whitelist расширений;
- путь в сообщении об ошибке мог раскрывать структуру ФС;
- вызов требовал вручную склеивать `'datas/html/' . ...` с обрезкой строки.

Новая `cot_readraw()`:

1. Принимает **весь** текст страницы.
2. Сама распознаёт префикс `include:`.
3. Читает файл **только** из `datas/html/page/`.
4. Блокирует path traversal, null-byte, symlink escape, небезопасные расширения.

**Приоритет правок — безопасность.** Удобный синтаксис `cot_readraw($pag['page_text'])` и плагин `pagehtml` появляются уже после того, как чтение файла сделано безопасным.

Без этих правок плагин всё равно можно установить, но директива `include:` в страницах останется на старой, слабой логике (если она ещё используется).

### 2.2. Полный код безопасной `cot_readraw`

Файл: **`modules/page/inc/page.functions.php`**

Замените (или добавьте) функцию целиком:

```php
/**
 * Обрабатывает текст страницы.
 * Если текст начинается с "include:" — безопасно читает указанный файл
 * из datas/html/page/. В остальных случаях возвращает исходный текст без изменений.
 *
 * Использование:
 *   $pag['page_text'] = cot_readraw($pag['page_text']);
 *
 * @param string $text Текст страницы
 * @return string
 */
function cot_readraw($text)
{
	// 1. Если это не include-директива — возвращаем текст как есть
	if (mb_substr($text, 0, 8) !== 'include:') {
		return $text;
	}

	// 2. Извлекаем относительный путь после "include:"
	//    Ограничиваем длину 255 символами (как было раньше)
	$relative = trim(mb_substr($text, 8, 255));

	// 3. Базовая директория: datas/html/page/
	$baseDir = realpath((Cot::$cfg['datas_dir'] ?? 'datas') . '/html/page');
	if ($baseDir === false) {
		return 'File not found';
	}

	// 4. Очистка входных данных
	$relative = str_replace(["\0", '\\'], ['', '/'], $relative); // null-byte + Windows-слеши
	$relative = ltrim($relative, '/');                           // запрет абсолютных путей

	// 5. Быстрый отсев опасных конструкций
	if (
		$relative === '' ||
		strpos($relative, '..') !== false ||
		strpos($relative, './') !== false
	) {
		return 'File not found';
	}

	// 6. Собираем полный путь и разрешаем его
	$fullPath = $baseDir . '/' . $relative;
	$realPath = realpath($fullPath);

	// 7. Главная защита: реальный путь должен лежать строго внутри baseDir
	if (
		$realPath === false ||
		!is_file($realPath) ||
		!is_readable($realPath) ||
		strpos($realPath, $baseDir) !== 0          // path traversal / symlink escape
	) {
		return 'File not found';
	}

	// 8. Разрешаем только безопасные расширения
	$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
	if (!in_array($ext, ['html', 'htm', 'txt', 'md'], true)) {
		return 'File not found';
	}

	// 9. Читаем файл
	$content = file_get_contents($realPath);
	return $content === false ? 'File not found' : $content;
}
```

### 2.3. Вызов в `page.main.php`

Файл: **`modules/page/inc/page.main.php`**

Фрагмент обработки `redir:` / `include:` должен выглядеть так (логика `redir:` может отличаться на вашей сборке; важна ветка `include:`):

```php
if (mb_substr($pag['page_text'], 0, 6) == 'redir:') {
	$env['status'] = '303 See Other';
	$redir = trim(str_replace('redir:', '', $pag['page_text']));
	$sql_page_update = Cot::$db->query(
		"UPDATE $db_pages SET page_filecount=page_filecount+1 WHERE page_id=$id"
	);
	if (preg_match('#^(http|ftp)s?://#', $redir)) {
		if (!cot_url_check($redir)) {
			cot_log('Blocked off-site page redir: target', 'sec', 'page', 'error');
			cot_die_message(403, TRUE);
		}
		header('Location: ' . $redir);
	} else {
		header('Location: ' . COT_ABSOLUTE_URL . ltrim($redir, '/'));
	}
	exit;
} elseif (mb_substr($pag['page_text'], 0, 8) == 'include:') {
	$pag['page_text'] = cot_readraw($pag['page_text']);
}
```

Раньше типичный вызов был:

```php
$pag['page_text'] = cot_readraw('datas/html/' . trim(mb_substr($pag['page_text'], 8, 255)));
```

Теперь путь и разбор `include:` — **внутри** функции. Снаружи передаётся только `$pag['page_text']`.

### 2.4. Что именно защищает новая реализация

| Угроза | Мера |
|--------|------|
| Directory Traversal (`../`) | `realpath` + `strpos($realPath, $baseDir) === 0` |
| Null-byte | Удаление `\0` |
| Абсолютные пути | `ltrim($relative, '/')` |
| Symlink escape | `realpath` + проверка префикса base |
| Чтение произвольных файлов | Каталог зафиксирован: `datas/html/page/` |
| Нежелательные типы файлов | Whitelist: `html`, `htm`, `txt`, `md` |
| Раскрытие путей | Всегда одна строка: `File not found` |

Плагин `pagehtml` использует **те же** принципы: `pagehtml_validate_file()` / `pagehtml_validate_dir()` с `realpath` и запретом выхода за `datas/html/page/`.

---

## 3. Как это работает

### 3.1. Архитектура

```
Посетитель открывает страницу Page
        ↓
page.main.php видит "include:..."
        ↓
cot_readraw($pag['page_text'])
        ↓
безопасное чтение datas/html/page/...
        ↓
контент файла → шаблон → HTML посетителю

Администратор
        ↓
admin.php?m=other&p=pagehtml
        ↓
pagehtml.tools.php (список / редактор)
        ↓
сохранение / создание / удаление → pagehtml.ajax.php
        ↓
файлы только внутри datas/html/page/
```

### 3.2. Структура плагина

```text
plugins/pagehtml/
├── pagehtml.setup.php           # метаданные + config
├── pagehtml.tools.php           # хук tools: список и редактор
├── pagehtml.ajax.php            # хук ajax: save / create / delete / download
├── inc/
│   └── pagehtml.functions.php   # API: validate, list, breadcrumbs
├── tpl/
│   ├── pagehtml.tools.tpl       # список файлов и папок
│   └── pagehtml.editor.tpl      # CodeMirror
└── lang/
    ├── pagehtml.ru.lang.php
    └── pagehtml.en.lang.php
```

### 3.3. Хуки

| Хук | Точка входа | Назначение |
|-----|-------------|------------|
| `tools` | `admin.php?m=other&p=pagehtml` | Список / редактор |
| `ajax` | `index.php?r=pagehtml` (через `cot_url('plug', ...)`) | save, create_file, create_dir, delete, download |

Собственных таблиц БД плагин не создаёт. При установке появляются только стандартные записи в `cot_core`, `cot_auth`, `cot_config`, `cot_plugins`.

### 3.4. Список файлов и навигация

- Без параметра `file` — режим списка.
- Параметр `dir` — относительный путь подпапки внутри `datas/html/page/`.
- Хлебные крошки: Корень → подпапка → …
- Кнопки: «Новый файл», «Новая папка».
- Удаление файла или **пустой** папки.

### 3.5. Редактор

- `?file=legal/privacy.html` — открывает CodeMirror.
- Режим: `htmlmixed` для html/htm, `markdown` для md, plain для txt.
- Сохранение: POST `a=save`, CSRF `x`, тело `content`.
- Скачивание: GET `a=download` + `x`.
- Удаление файла из редактора с подтверждением.

### 3.6. Параметры URL

**Админка (tools):**

| Параметр | Описание |
|----------|----------|
| `m=other` | Раздел «Инструменты» |
| `p=pagehtml` | Код плагина |
| `dir` | Текущая подпапка (опционально) |
| `file` | Относительный путь к файлу для редактора |

**AJAX:**

| Параметр | Описание |
|----------|----------|
| `r=pagehtml` | Роутинг на плагин |
| `a` | `save` \| `create_file` \| `create_dir` \| `delete` \| `download` |
| `file` | Относительный путь к файлу |
| `dir` | Относительный путь к папке |
| `name` | Имя нового файла/папки |
| `content` | Содержимое (только save) |
| `x` | CSRF-токен |

### 3.7. Безопасность плагина

- Двойная проверка прав: `cot_auth('admin','a','A')` **или** `cot_auth('plug','pagehtml','A')`.
- CSRF: `cot_check_xp()` (POST), `cot_check_xg(false)` (download GET).
- Все пути проходят `pagehtml_normalize_rel` → `realpath` → проверка префикса base.
- Whitelist расширений (с учётом настройки `allow_md`).
- Имена файлов/папок без `..`, `/`, `\`, управляющих символов.
- Корневую `datas/html/page/` удалить нельзя.
- Непустую папку удалить нельзя.
- Ответы об ошибках без раскрытия абсолютных путей сервера.

По умолчанию после установки доступ только у супер-админа (`Auth_members` пустой, `Lock_members=RW12345A`). Расширение прав — осознанно через SQL или правку setup и переустановку.

---

## 4. Пошаговая установка

### 4.1. Правки модуля Page (обязательно для безопасного `include:`)

1. Внесите функцию `cot_readraw` из раздела [2.2](#22-полный-код-безопасной-cot_readraw) в `modules/page/inc/page.functions.php`.
2. Обновите ветку `include:` в `modules/page/inc/page.main.php` как в [2.3](#23-вызов-в-pagemainphp).
3. Убедитесь, что каталог `datas/html/page/` существует и доступен веб-серверу (плагин создаст его при первом обращении, если хватит прав).

### 4.2. Установка плагина

1. Сделайте бэкап сайта.
2. Загрузите папку `pagehtml/` в `plugins/` сайта.
3. Админка → **Extensions → Plugin Manager** → **Install** у «Page HTML Files».
4. Откройте **Tools → HTML-файлы страниц** (`admin.php?m=other&p=pagehtml`).

### 4.3. Права

По умолчанию UI прав для не-SA заблокирован (`Lock_members=RW12345A`).  
Выдать группе, например id=6, право A:

```sql
UPDATE cot_auth
SET auth_rights = 128
WHERE auth_code = 'plug' AND auth_option = 'pagehtml' AND auth_groupid = 6;
```

Либо до установки в `pagehtml.setup.php` задать `Auth_members=RWA` и `Lock_members=` и установить плагин.

### 4.4. Проверка

1. Создайте папку `test` и файл `hello.html` через плагин.
2. Создайте/откройте страницу Page, в тексте:

   ```text
   include:test/hello.html
   ```

3. Откройте страницу на сайте — должно отобразиться содержимое файла (после правок `cot_readraw`).

---

## 5. Пошаговое использование

### 5.1. Список

`admin.php?m=other&p=pagehtml` — корень `datas/html/page/`.  
Клик по папке — вход внутрь. Крошки — возврат наверх.

### 5.2. Новый файл

«Новый файл» → имя с расширением, например `about.html` или `docs/intro.md` (имя без пути; текущая папка берётся из `dir`).

### 5.3. Новая папка

«Новая папка» → имя без слэшей.

### 5.4. Редактирование

Клик по файлу → CodeMirror → **Сохранить** или Ctrl+S / Cmd+S.

### 5.5. Скачать копию

Кнопка «Скачать копию» — текущее содержимое с диска (не история версий).

### 5.6. Удаление

В списке или в редакторе — с подтверждением. Папка удаляется только если пуста.

### 5.7. Связка со страницей Cotonti

В поле текста страницы модуля Page:

```text
include:legal/privacy.html
```

Путь **относительно** `datas/html/page/`.  
После сохранения страницы посетитель видит содержимое файла, если сработали правки `cot_readraw` / `page.main.php`.

---

## 6. Конфигурация плагина

**Extensions → Plugin Manager → Page HTML Files → Configure**

| Опция | Тип | По умолчанию | Описание |
|-------|-----|--------------|----------|
| `allow_md` | radio 0/1 | 1 | Разрешить работу с `.md` в плагине |

Чтение `.md` через `cot_readraw` зависит от whitelist в самой функции (в рекомендуемом коде `.md` уже разрешён). Если в `cot_readraw` убрать `md`, плагин может создавать `.md`, но страница через `include:` их не отдаст.

---

## 7. Руководство по безопасности

1. **Сначала безопасность чтения** — правки `cot_readraw` и вызова в `page.main.php` обязательны, если используете `include:`.
2. **Не раздавайте право A** на `pagehtml` всем подряд: это прямой доступ к записи файлов на сервере.
3. **Бэкапы** плагин не ведёт. Перед массовыми правками — git, архив, «Скачать копию».
4. **HTTPS** — контент и CSRF уходят POST-ом.
5. **Права ФС** — `datas/` не должен быть исполняемым как PHP (типичная настройка веб-сервера: не обрабатывать PHP внутри `datas`).
6. **Не отключайте CSRF** в `pagehtml.ajax.php`.
7. **Lock в setup** — по умолчанию сознательный fail-safe; расширяйте доступ только осознанно.

---

## 8. Troubleshooting

| Симптом | Возможная причина | Что проверить |
|---------|-------------------|---------------|
| Плагина нет в Tools | Не установлен / нет хука tools | Plugin Manager, таблица `cot_plugins` |
| 403 при открытии | Нет права A | `cot_auth` / SQL на `plug` + `pagehtml` |
| «Base directory not found» | Нет `datas/html/page` и нет прав создать | Права на `datas/`, `datas_dir` в конфиге |
| include: не подставляет файл | Не обновлены `page.functions.php` / `page.main.php` | Код из раздела 2 |
| File not found на сайте | Неверный путь, расширение, права чтения | Путь относительно `datas/html/page/`, whitelist |
| Save: Invalid CSRF | Нет / устарел `x` | Обновить страницу редактора |
| Cannot delete folder | Папка не пуста | Удалить файлы внутри |
| CodeMirror пустой | CDN jsdelivr недоступен | Сеть / CSP / локальная копия CodeMirror |

---

## 9. FAQ

**Q: Плагин сам подключает файлы к страницам?**  
A: Нет. В тексте страницы вы пишете `include:...`. Читает файл `cot_readraw` при показе страницы.

**Q: Можно ли без правок модуля Page?**  
A: Плагин установится и будет редактировать файлы. Но безопасный и удобный `include:` требует правок из раздела 2. Без них вы либо на старой уязвимой логике, либо без `include:` вовсе.

**Q: Почему сначала безопасность, а не «удобный редактор»?**  
A: Редактор без безопасного чтения не устраняет риск path traversal в `include:`. Сначала закрывается чтение, затем добавляется инструмент управления файлами.

**Q: Поддерживается ли `.php`?**  
A: Нет. Whitelist только html/htm/txt/md. Иначе это был бы произвольный доступ к исполняемому коду.

**Q: Есть ли история версий?**  
A: Нет. Только скачивание текущего файла. Для истории — git или внешний бэкап.

**Q: Работает ли на Cotonti 0.9.x?**  
A: Нет. Нужен API 1.0.x.

**Q: Что будет при uninstall?**  
A: Удалятся записи плагина в БД. Файлы в `datas/html/page/` и правки модуля Page **останутся**. Каталог `plugins/pagehtml/` удалите вручную при необходимости.

---

## 10. Обновление и удаление

**Обновление:** бэкап → заменить файлы плагина → Extensions → Update (при смене Version в setup).

**Удаление:** Extensions → Uninstall → удалить `plugins/pagehtml/` с диска.  
Файлы контента в `datas/html/page/` и код `cot_readraw` в модуле Page не откатываются автоматически.

---

## 11. Roadmap

- История сохранений (ограниченное число `.bak`)
- Поиск по содержимому файлов
- Загрузка файла с компьютера (upload) с теми же проверками расширений
- Опциональный локальный CodeMirror без CDN
- Diff текущего файла с последней скачанной копией

---

## 12. Лицензия

BSD. Автор: webitproff, 2026.

Cotonti — отдельный проект со своей лицензией. CodeMirror 5 подключается с CDN (MIT).

---

### Краткий чеклист перед продакшеном

1. [ ] `cot_readraw` из раздела 2.2 в `page.functions.php`
2. [ ] Вызов `cot_readraw($pag['page_text'])` в `page.main.php`
3. [ ] Плагин установлен и открывается в Tools
4. [ ] `datas/html/page/` доступен на чтение/запись веб-серверу
5. [ ] Права A на плагин только у доверенных администраторов
6. [ ] Проверен сценарий: файл в плагине → `include:` в странице → вывод на сайте

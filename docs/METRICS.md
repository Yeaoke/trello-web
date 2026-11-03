# Каталог метрик

Scrape и вычисление правил происходят каждые 15 секунд. Grafana использует datasource
prometheus. Здесь перечислены все группы перенесённого мониторинга; экспортеры также
отдают собственные служебные process/go/scrape-метрики.

## Приложение

| Метрика | Тип / labels | Смысл |
|---|---|---|
| http_requests_total | Counter; method, route, status | Завершённые запросы PHP. Статика nginx и /metrics.php исключены, health включён |
| http_request_duration_seconds | Histogram; method, route | Время PHP до shutdown callback, секунды. Без очереди FPM и сетевой доставки |
| app_notes_created_total | Counter без labels | Успешные INSERT заметок с момента рестарта FPM/APCu, не размер таблицы |
| app_note_write_operations_total | Counter; outcome=success/error | Завершённые валидные попытки сохранения; HTTP 422 не считается |
| app_note_write_duration_seconds | Histogram; outcome=success/error | Длительность ORM-сохранения, включая ленивое подключение к БД и INSERT, секунды |

Гистограммы экспортируют _bucket{le}, _sum, _count. Бакеты накопительные; +Inf
включает все наблюдения. Границы записи: 0.005, 0.01, 0.025, 0.05, 0.1, 0.25,
0.5, 1, 2, 5 секунд и +Inf. HTTP-бакеты приведены в instrumentation/prepend.php.
Метод ограничен стандартными HTTP-методами или OTHER, route — /index.php,
/health.php или other. Текст заметки, URL query string, ID и исключения не попадают
в labels. Ошибка хранилища метрик логируется и не должна ломать сохранение.

## Инфраструктура

| Источник / семейства | Что показывают |
|---|---|
| up, scrape_duration_seconds, scrape_samples_scraped | Успешность и стоимость сбора. up=1 означает только успешный scrape |
| probe_success, probe_duration_seconds, probe_http_status_code | HTTP health с SELECT 1 и отдельная nginx-health. Пробы из Docker-сети, без проверки порта хоста |
| nginx_up, nginx_connections_active/reading/writing/waiting, nginx_http_requests_total, nginx_connections_accepted/handled | stub_status, соединения и запросы nginx, включая служебный трафик |
| phpfpm_up, phpfpm_active_processes, phpfpm_idle_processes, phpfpm_total_processes | Доступность и занятость пула |
| phpfpm_listen_queue, phpfpm_max_children_reached, phpfpm_slow_requests | Очередь и накопленные события насыщения/медленных запросов |
| pg_up, pg_stat_activity_count, pg_settings_max_connections | Подключение экспортера к БД, текущие соединения и лимит |
| pg_stat_database_xact_commit/rollback | Накопленные транзакции, rate даёт TPS |
| pg_stat_database_*, pg_locks_*, pg_stat_bgwriter_* | Статистика баз, блокировок и фоновой записи; набор зависит от PostgreSQL |
| pg_stat_statements_seconds_total, pg_stat_statements_calls_total | Время и число вызовов SQL по queryid; SQL-текст выключен |
| ALERTS{alertstate} | pending/firing от самого Prometheus |
| prometheus_*, alertmanager_*, process_*, go_* | Самонаблюдение компонентов мониторинга |
| container_cpu_usage_seconds_total, container_memory_working_set_bytes, container_network_* | Опциональный cAdvisor: CPU/RAM/network, не включён автоматически |

На PostgreSQL 18 старые community bgwriter-панели могут быть пустыми: часть статистики
переехала в pg_stat_checkpointer. Это не ноль. Основной обзор использует соединения,
транзакции и SQL latency. Счётчики снимаются экспортерами с отдельного пользователя
pg_monitor, а не с учётной записи приложения.

## PromQL и интерпретация

Скорость попыток по исходам, операций/с:
```promql
sum by (outcome) (rate(app_note_write_operations_total{job="app"}[5m]))
```

Доля ошибок (0–1; Grafana показывает проценты):
```promql
sum(rate(app_note_write_operations_total{job="app",outcome="error"}[5m]))
/
sum(rate(app_note_write_operations_total{job="app"}[5m]))
```

Оценка числа ошибок за 5 минут:
```promql
sum(increase(app_note_write_operations_total{job="app",outcome="error"}[5m]))
```

p95 сохранения по исходам:
```promql
histogram_quantile(0.95,
  sum by (le, outcome) (rate(app_note_write_duration_seconds_bucket{job="app"}[5m]))
)
```

Среднее время:
```promql
sum by (outcome) (rate(app_note_write_duration_seconds_sum[5m]))
/
sum by (outcome) (rate(app_note_write_duration_seconds_count[5m]))
```

Сначала rate, затем sum: так учитывается сброс каждого counter. increase экстраполирует
границы окна и может быть дробным. p95 приблизителен и зависит от бакетов; среднее
не равно p95. Без нагрузки доля и p95 не определены. Отсутствие ряда не означает
исправность. Дашборды используют $__rate_interval с учётом scrape interval.

## Алерты

| Алерт | Условие | for |
|---|---|---|
| TargetDown | up=0 | 1m |
| HealthCheckFailing | probe_success=0 | 1m |
| HighErrorRate | 5xx >5%, трафик >0.2 RPS | 2m |
| HighLatencyP95 | p95 HTTP по route >1s | 3m |
| PhpFpmPoolSaturated | очередь >0 или рост max_children_reached за 1m | 2m |
| PostgresDown | pg_up=0 | 30s |
| PostgresTooManyConnections | соединения / max_connections >80% | 2m |
| NoteWriteErrors | increase(error[1m]) >0 | 2m |

NoteWriteErrors требует непрерывного выполнения условия. Окно 1m меньше for 2m,
поэтому одиночная ошибка обычно не вызывает firing. После прекращения ошибок алерт
погаснет, когда они выйдут из окна. Без POST этот алерт не проверяет запись.
Alertmanager группирует уведомления, повторяет через час, отправляет resolved и
подавляет health-симптом при PostgresDown. Mailpit — локальная учебная почта.

## Проверка

1. Запустите стенд по README. Prometheus → Status → Targets: все 9 targets UP,
   включая две blackbox-пробы.
2. Отправьте несколько заметок. После двух и более scrape появятся rate-графики;
   counters доступны сразу на внутреннем /metrics.php.
3. Запустите tests/monitoring.php --allow-fault-injection внутри app с обоими
   compose-файлами. Триггер отклоняет только уникальный тестовый заголовок;
   проверяются успех, валидация, ошибка с 302, зелёный health и восстановление.
4. Выполните promtool test rules /etc/prometheus/tests/note-writes.yml: длительные
   ошибки вызывают алерт, простой, одиночная ошибка и сброс — нет.

Для демонстрации живого NoteWriteErrors повторяйте прямой HTTP POST с заголовком
длиннее 255 символов более двух минут на учебном стенде: HTML maxlength обходится,
БД отклоняет INSERT, HTTP остаётся 302, health — 200. Остановите запросы и дождитесь
inactive, затем отправьте обычную заметку. Это не требует read-only для всей БД.

```powershell
docker compose -f compose.yaml -f compose.monitoring.yaml exec nginx wget -qO- http://localhost:8081/metrics.php
```

Ссылки runbook_url в правилах направлены в docs/runbooks репозитория Yeaoke/trello-web;
пока локальные изменения не опубликованы, используйте соответствующие локальные файлы.

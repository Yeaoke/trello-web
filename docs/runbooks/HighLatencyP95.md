# HighLatencyP95

**Что значит.** Оценка 95-го перцентиля времени исполнения PHP для route выше 1 секунды
уже 3 минуты. Это приблизительная оценка по бакетам, а не гарантия о каждом 20-м запросе.

**Как проверить.**
- Дашборд «Обзор сервиса» → «Duration: p95 по route».
- Медленная БД? Панель «Среднее время запроса, топ-5» и статистика PostgreSQL
  (`docker compose -f compose.yaml -f compose.monitoring.yaml logs db | grep duration`), либо `make psql`:
  `SELECT query, calls, mean_exec_time FROM pg_stat_statements ORDER BY mean_exec_time DESC LIMIT 5;`
- Нехватка воркеров? Если одновременно горит PhpFpmPoolSaturated — запросы ждут
  в очереди (это время в метрике PHP даже не видно).

**Что делать.** Найти медленный запрос/участок кода; при насыщении пула —
снизить нагрузку или увеличить `pm.max_children` (если хватает CPU/памяти и
соединений к БД).

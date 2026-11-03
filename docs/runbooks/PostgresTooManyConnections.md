# PostgresTooManyConnections

**Что значит.** Занято больше 80% `max_connections` (лимит берётся из pg_settings_max_connections). Когда лимит кончится, новые подключения получат
`FATAL: sorry, too many clients already`.

**Как проверить.**
- Дашборд «Обзор сервиса» → «Соединения по состоянию».
- `make psql`:
  `SELECT usename, application_name, state, count(*) FROM pg_stat_activity GROUP BY 1, 2, 3 ORDER BY 4 DESC;`
- Много `idle in transaction` — код открывает транзакцию и не закрывает.

**Что делать.**
- Найти, кто держит соединения, и починить утечку (не закрытые
  соединения/транзакции, слишком большой пул в приложении).
- Срочно — завершить зависшие сессии: `SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE state = 'idle in transaction' AND state_change < now() - interval '5 minutes';`
- Поднимать `max_connections` — последнее средство: каждое соединение в
  PostgreSQL — отдельный процесс и память. Обычно ставят пулер (PgBouncer).

# PostgresDown

**Что значит.** postgres-exporter дольше 30 секунд не может подключиться к
PostgreSQL (`pg_up == 0`). Приложение без БД не работает: этот алерт
подавляет HealthCheckFailing (inhibit_rules в Alertmanager), чтобы
дежурный получил одно письмо о причине, а не пачку о симптомах.

**Как проверить.**
- `docker compose -f compose.yaml -f compose.monitoring.yaml ps` — запущен ли контейнер db и healthy ли он.
- `docker compose -f compose.yaml -f compose.monitoring.yaml logs --tail 50 db` — причина остановки (нехватка
  места, ошибка в `postgresql.conf`, OOM).
- `docker compose -f compose.yaml -f compose.monitoring.yaml exec db pg_isready`.

**Что делать.**
- Контейнер остановлен — `docker compose -f compose.yaml -f compose.monitoring.yaml up -d`.
- Не стартует после правки конфига — откатить параметры db в compose.monitoring.yaml.
- Кончилось место на диске/томе — освободить место (`docker system df`).

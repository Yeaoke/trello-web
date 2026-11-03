# HealthCheckFailing

**Что значит.** Black-box проба дольше минуты получает неуспешный ответ:
пользователи видят ошибку. Это алерт на *симптом* — причина может быть любой.

**Как проверить.**
- `curl -i http://127.0.0.1:8090/health.php` — код и тело. `503` и
  `"status":"error"` — проблема с БД; `502` — nginx не достучался до php-fpm.
- `curl -i http://127.0.0.1:8090/nginx-health` — жив ли сам nginx.
- В Prometheus: `probe_http_status_code`, `probe_failed_due_to_regex`
  (ответ 200, но тело не то).

**Что делать.** Идти по цепочке nginx → php-fpm → PostgreSQL: `docker compose -f compose.yaml -f compose.monitoring.yaml ps`,
логи сервиса, который отвечает с ошибкой. Если одновременно горит
PostgresDown — этот алерт подавлен (inhibit) и причину сразу видно в нём.

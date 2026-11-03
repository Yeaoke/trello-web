# Runbook-и алертов

На каждый алерт из `docker/prometheus/rules/alerts.yml` — короткая инструкция:
что значит, как проверить, что делать. Ссылка на runbook приходит в письме
(аннотация `runbook_url`), чтобы дежурный не начинал разбор с нуля.

| Алерт | Severity |
|---|---|
| [TargetDown](TargetDown.md) | warning |
| [HealthCheckFailing](HealthCheckFailing.md) | critical |
| [HighErrorRate](HighErrorRate.md) | critical |
| [HighLatencyP95](HighLatencyP95.md) | warning |
| [PhpFpmPoolSaturated](PhpFpmPoolSaturated.md) | warning |
| [PostgresDown](PostgresDown.md) | critical |
| [PostgresTooManyConnections](PostgresTooManyConnections.md) | warning |

| [NoteWriteErrors](NoteWriteErrors.md) | warning |

<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$logger = \App\Logger::get();
\App\Http\RequestLog::register($logger);

try {
    \App\Database::boot();
} catch (\Throwable $e) {
    $logger->error('database.boot_failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    exit('БД недоступна. Увы');
}

// Шорткатик экранирования
function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// POST
$validationError = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));

    if ($title === '' || $content === '') {
        http_response_code(422);
        $validationError = 'Заполните все поля';
    } else {
        try {
            \App\Models\Note::create(['title' => $title, 'content' => $content]);
        } catch (\Throwable $e) {
            $logger->error('note.create_failed', ['error' => $e->getMessage()]);
            http_response_code(302);
            header('Location: /?err=1');
            exit;
        }
        http_response_code(302);
        header('Location: /');
        exit;
    }
}

// ------------------------------------------------------------------------------------------ //

$notes = \App\Models\Note::query()->orderByDesc('id')->get();

$pgError = null;
$pgVersion = null;
try {
    $row = \App\Database::connection()->selectOne('SELECT version()');
    $pgVersion = (string) ($row->version ?? 'unknown');
} catch (\Throwable $e) {
    $logger->error('database.version_failed', ['error' => $e->getMessage()]);
    $pgError = $e->getMessage();
}

$showErrBanner = ($_GET['err'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#15141a">
<title>webl — заметки</title>
<style>
:root {
    color-scheme: dark;
    --bg: #15141a;
    --bg-2: #121117;
    --surface: #1e1d24;
    --surface-2: #26252e;
    --surface-3: #302f3a;
    --line: #34333f;
    --line-strong: #4b4a59;
    --ink: #f4f4f6;
    --ink-2: #b8b7c2;
    --ink-3: #8f8e9b;
    --accent: #a78bfa;
    --accent-hover: #bdadff;
    --accent-soft: color-mix(in srgb, var(--accent) 32%, transparent);
    --accent-tint: color-mix(in srgb, var(--accent) 12%, transparent);
    --on-accent: #171224;
    --safe: #4fd6a6;
    --warn: #eec25f;
    --danger: #ff8a75;
    --float: 0 16px 40px -16px rgb(0 0 0 / .5), 0 2px 6px rgb(0 0 0 / .25);
    --ease: cubic-bezier(.22, 1, .36, 1);
    --mono: ui-monospace, "SF Mono", SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
    --sans: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100dvh;
    font: 16px/1.6 var(--sans);
    color: var(--ink);
    background: radial-gradient(760px 380px at 50% -6%, color-mix(in srgb, var(--accent) 8%, transparent), transparent 70%), var(--bg);
    -webkit-font-smoothing: antialiased;
    letter-spacing: .01em;
}
::selection { background: color-mix(in srgb, var(--accent) 32%, transparent); }

.wrap {
    width: min(100% - 32px, 880px);
    margin-inline: auto;
    padding-block: clamp(36px, 7vh, 56px) clamp(48px, 9vh, 84px);
    padding-top: 20px
}

.eyebrow {
    margin: 0 0 8px;
    font: 600 11px/1.4 var(--mono);
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-3);
}
.eyebrow--accent { color: var(--accent); }

.masthead h1 {
    margin: 0;
    font-size: clamp(1.9rem, 5vw, 2.25rem);
    font-weight: 700;
    line-height: 1.1;
    letter-spacing: -.02em;
}
.masthead .dash { color: var(--accent); font-weight: 500; }

main { display: grid; gap: 18px; margin-top: 1em; }

.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: var(--float);
    padding: clamp(20px, 4vw, 28px);
}
.card-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}
.card-head h2 { margin: 0; font-size: 1.3rem; font-weight: 650; line-height: 1.25; letter-spacing: -.01em; }
.count {
    flex: none;
    font: 500 11px/1 var(--mono);
    letter-spacing: .06em;
    color: var(--ink-2);
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: 6px 11px;
}

.alerts { display: grid; gap: 12px; }
.alert {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px 14px;
    padding: 13px 18px;
    border: 1px solid;
    border-radius: 14px;
    background: var(--surface);
    font-size: 15px;
}
.alert-tag { font: 600 10.5px/1.6 var(--mono); letter-spacing: .14em; text-transform: uppercase; }
.alert--danger { border-color: color-mix(in srgb, var(--danger) 38%, transparent); background: color-mix(in srgb, var(--danger) 8%, var(--surface)); }
.alert--danger .alert-tag { color: var(--danger); }
.alert--warn { border-color: color-mix(in srgb, var(--warn) 38%, transparent); background: color-mix(in srgb, var(--warn) 8%, var(--surface)); }
.alert--warn .alert-tag { color: var(--warn); }

form { display: grid; gap: 16px; }
label {
    display: block;
    margin-bottom: 7px;
    font: 600 11px/1 var(--mono);
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--ink-3);
}
input[type="text"], textarea {
    width: 100%;
    font: inherit;
    color: var(--ink);
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 11px 14px;
    transition: border-color .18s var(--ease), box-shadow .18s var(--ease);
}
textarea { min-height: 150px; resize: vertical; line-height: 1.6; }
input[type="text"]::placeholder, textarea::placeholder { color: var(--ink-3); }
input[type="text"]:hover, textarea:hover { border-color: var(--line-strong); }
input[type="text"]:focus-visible, textarea:focus-visible {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}
.btn {
    justify-self: start;
    font: 650 15px/1 var(--sans);
    color: var(--on-accent);
    background: var(--accent);
    border: 0;
    border-radius: 12px;
    padding: 12px 22px;
    cursor: pointer;
    transition: background-color .18s var(--ease), transform .18s var(--ease);
}
.btn:hover { background: var(--accent-hover); }
.btn:active { transform: translateY(1px); }
.btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

.notes { display: flex; flex-direction: column; }
.note { padding: 18px 0; border-top: 1px solid var(--line); }
.note:first-child { padding-top: 0; border-top: 0; }
.note:last-child { padding-bottom: 0; }
.note-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    font: 500 11.5px/1 var(--mono);
    letter-spacing: .05em;
    color: var(--ink-3);
}
.note-id {
    color: var(--accent);
    background: var(--accent-tint);
    border: 1px solid color-mix(in srgb, var(--accent) 32%, transparent);
    border-radius: 999px;
    padding: 4px 10px;
}
.note-title { margin: 11px 0 4px; font-size: 1.0625rem; font-weight: 650; letter-spacing: -.01em; overflow-wrap: anywhere; }
.note-body { margin: 0; color: var(--ink-2); font-size: 15px; white-space: pre-wrap; overflow-wrap: anywhere; }

.empty {
    border: 1px dashed var(--line-strong);
    background: var(--bg-2);
    border-radius: 14px;
    padding: clamp(36px, 7vw, 56px) 24px;
    text-align: center;
}
.empty .eyebrow { color: var(--accent); margin-bottom: 12px; }
.empty-title { margin: 0; font-size: 1.0625rem; font-weight: 650; }
.empty-sub { margin: 8px 0 0; font-size: 14.5px; color: var(--ink-3); }

.env { margin: 0; }
.env-row { display: flex; flex-wrap: wrap; gap: 4px 24px; align-items: baseline; padding: 12px 0; border-top: 1px solid var(--line); }
.env-row:first-child { padding-top: 0; border-top: 0; }
.env-row:last-child { padding-bottom: 0; }
.env-row dt { flex: 0 0 150px; font: 600 11px/1.5 var(--mono); letter-spacing: .09em; text-transform: uppercase; color: var(--ink-3); }
.env-row dd { flex: 1 1 240px; margin: 0; font: 400 13.5px/1.5 var(--mono); overflow-wrap: anywhere; }
.env-row dd.is-error { color: var(--danger); }

.env-card { margin-top: 18px; }
:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

@keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.masthead { animation: rise .45s var(--ease) both; }
main { animation: rise .45s var(--ease) .07s both; }
.env-card { animation: rise .45s var(--ease) .14s both; }

@media (max-width: 560px) {
    .card-head { align-items: center; }
    .env-row dt { flex-basis: 100%; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition-duration: .01ms !important; }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="masthead">
        <div class="eyebrow eyebrow--accent">WebLab <span class="dash">&middot;</span> заметки</div>
        <!--<h1>WebLab <span class="dash">&middot;</span> заметки</h1>-->
    </header>

    <main>
        <?php if ($showErrBanner || $validationError !== null): ?>
        <div class="alerts">
            <?php if ($showErrBanner): ?>
                <div class="alert alert--danger" role="alert">
                    <span class="alert-tag">ошибка</span>Не удалось сохранить заметку
                </div>
            <?php endif; ?>
            <?php if ($validationError !== null): ?>
                <div class="alert alert--warn" role="alert">
                    <span class="alert-tag">проверка</span>
                    <?= h($validationError) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <section class="card" aria-labelledby="compose-title">
            <div class="card-head">
                <div>
                    <h2 id="compose-title">Новая заметка</h2>
                </div>
            </div>
            <form method="post" action="/">
                <div class="field">
                    <label for="title">Название</label>
                    <input type="text" id="title" name="title" required maxlength="255" placeholder="Домашнее задание">
                </div>
                <div class="field">
                    <label for="content">Текст</label>
                    <textarea id="content" name="content" required placeholder="Реализовать статический конфиг с возможность добавлять зашифрованные значения"></textarea>
                </div>
                <button type="submit" class="btn">Добавить</button>
            </form>
        </section>

        <section class="card" aria-labelledby="notes-title">
            <div class="card-head">
                <div>
                    <h2 id="notes-title">Заметки</h2>
                </div>
                <span class="count" aria-hidden="true"><?= h((string) $notes->count()) ?></span>
            </div>

            <?php if ($notes->isEmpty()): ?>
                <div class="empty">
                    <p class="eyebrow">Заметок нет</p>
                </div>
            <?php else: ?>
                <div class="notes">
                    <?php foreach ($notes as $note): ?>
                        <article class="note">
                            <div class="note-meta">
                                <span class="note-id">№ <?= h((string) $note->id) ?></span>
                                <span><?= h((string) $note->created_at) ?></span>
                            </div>
                            <h3 class="note-title"><?= h((string) $note->title) ?></h3>
                            <p class="note-body"><?= h((string) $note->content) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>

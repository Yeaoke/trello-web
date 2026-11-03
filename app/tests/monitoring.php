<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

// Explicit local fault injection. Only the unique marker is rejected by the trigger.
if (!in_array('--allow-fault-injection', $argv, true)) {
    fwrite(STDERR, "Use --allow-fault-injection on the local lab.\n"); exit(2);
}
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
function request(string $path, ?array $post = null, int $port = 80): array {
    $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10];
    if ($post !== null) {
        $options += ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($post)];
    }
    $body = file_get_contents("http://nginx:$port$path", false, stream_context_create(['http' => $options]));
    $headers = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), (string) $body];
}
function metric(string $name, string $labels = ''): float {
    [$status, $body] = request('/metrics.php', null, 8081);
    check($status === 200, 'metrics unavailable');
    preg_match('/^' . preg_quote($name . $labels, '/') . ' ([0-9.eE+-]+)$/m', $body, $m);
    check(isset($m[1]), "missing $name$labels");
    return (float) $m[1];
}
$db = \App\Database::connection();
$marker = 'monitoring_' . bin2hex(random_bytes(8));
$trigger = $marker . '_trigger';
$function = $marker . '_reject';
$success = metric('app_note_write_operations_total', '{outcome="success"}');
$errors = metric('app_note_write_operations_total', '{outcome="error"}');
$created = metric('app_notes_created_total');
check(request('/')[0] === 200, 'index is not healthy');
check(request('/metrics.php')[0] === 404, 'metrics exposed on public port');
check(request('/', ['title' => '', 'content' => ''])[0] === 422, 'validation status');
check(metric('app_note_write_operations_total', '{outcome="error"}') === $errors, 'validation counted as write failure');
try {
    check(request('/', ['title' => $marker, 'content' => 'ok'])[0] === 302, 'success redirect');
    check(metric('app_note_write_operations_total', '{outcome="success"}') === $success + 1, 'success count');
    check(metric('app_notes_created_total') === $created + 1, 'created count');
    check($db->table('notes')->where('title', $marker)->count() === 1, 'note not persisted');
    // Marker is generated locally from hex only, never taken from user input.
    $db->unprepared("CREATE FUNCTION $function() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.title = '$marker' THEN RAISE EXCEPTION 'monitoring test rejection'; END IF; RETURN NEW; END \$\$");
    $db->unprepared("CREATE TRIGGER $trigger BEFORE INSERT ON notes FOR EACH ROW EXECUTE FUNCTION $function()");
    check(request('/', ['title' => $marker, 'content' => 'fail'])[0] === 302, 'failure must reproduce hidden HTTP 302');
    check(request('/health.php')[0] === 200, 'SELECT 1 should remain healthy');
    check(metric('app_note_write_operations_total', '{outcome="error"}') === $errors + 1, 'error count');
    check(metric('app_note_write_operations_total', '{outcome="success"}') === $success + 1, 'failed insert counted as success');
    check(metric('app_notes_created_total') === $created + 1, 'failed insert counted as created');
    check(metric('app_note_write_duration_seconds_count', '{outcome="error"}') >= 1, 'error histogram');
    check($db->table('notes')->where('title', $marker)->count() === 1, 'rejected note persisted');
    $db->unprepared("DROP TRIGGER $trigger ON notes");
    check(request('/', ['title' => $marker, 'content' => 'recovered'])[0] === 302, 'recovery redirect');
    check(metric('app_note_write_operations_total', '{outcome="success"}') === $success + 2, 'recovery count');
    check($db->table('notes')->where('title', $marker)->count() === 2, 'recovery persistence');
    echo "PASS: success, validation, hidden INSERT failure, histogram, recovery, private metrics endpoint\n";
} finally {
    $db->unprepared("DROP TRIGGER IF EXISTS $trigger ON notes");
    $db->unprepared("DROP FUNCTION IF EXISTS $function()");
    $db->table('notes')->where('title', $marker)->delete();
}

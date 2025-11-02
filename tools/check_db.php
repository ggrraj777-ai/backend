<?php
function parseEnv($path){
    $env = [];
    if(!file_exists($path)) return $env;
    foreach(file($path) as $line){
        $line = trim($line);
        if($line === '' || str_starts_with($line, '#')) continue;
        $parts = explode('=', $line, 2);
        if(count($parts) === 2){
            [$k,$v] = $parts;
            $v = trim($v);
            if ((str_starts_with($v,'"') && str_ends_with($v,'"')) || (str_starts_with($v,"'") && str_ends_with($v,"'"))) {
                $v = substr($v,1,-1);
            }
            $env[$k] = $v;
        }
    }
    return $env;
}

$env = parseEnv(__DIR__ . '/../.env');
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int)($env['DB_PORT'] ?? 3306);
$db   = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$dsn = "mysql:host={$host};port={$port};dbname={$db}";
$result = [
  'host' => $host,
  'port' => $port,
  'database' => $db,
  'username' => $user,
  'status' => 'unknown',
  'error' => null,
];
try {
    $opt = [
        PDO::ATTR_TIMEOUT => 10,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opt);
    $stmt = $pdo->query('SELECT 1 as ok');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $result['status'] = ($row && (int)$row['ok'] === 1) ? 'ok' : 'unexpected';
} catch (Throwable $e) {
    $result['status'] = 'error';
    $result['error'] = $e->getMessage();
}
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);

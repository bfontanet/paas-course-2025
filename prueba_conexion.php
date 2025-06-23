<?php
// Recuperar variables de entorno
$dbHost = getenv('DB_HOST');
$dbName = "prueba";
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');

if (!$dbHost || !$dbUser || $dbPass === false) {
    throw new \RuntimeException('Faltan variables de entorno para la conexión a la base de datos.');
}

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

// Opciones PDO con SSL para Azure
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/BaltimoreCyberTrustRoot.crt.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de conexión MySQL</title>
    <style>
        body {
            background-color: #f2f7f5;
            font-family: 'Segoe UI', sans-serif;
            color: #333;
            padding: 2em;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border-radius: 10px;
            padding: 2em;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            color: #2e7d32;
            font-weight: bold;
        }
        .error {
            color: #c62828;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Conexión a la base de datos</h1>
        <?php
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            $stmt = $pdo->query('SELECT NOW() AS fecha_actual;');
            $fila = $stmt->fetch();
            echo "<p class='success'>Conectado correctamente.<br>🕒 Hora del servidor: <strong>" . htmlspecialchars($fila['fecha_actual']) . "</strong></p>";
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            echo "<p class='error'>❌ Error al conectar con la base de datos:<br>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
</body>
</html>

<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("SELECT NOW()");
    $result = $stmt->fetch();

    echo "<h2 style='color: green;'>✅ Conexión exitosa a Neon Database</h2>";
    echo "Hora del servidor PostgreSQL: " . $result['now'];
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Error ejecutando consulta</h2>";
    echo $e->getMessage();
}

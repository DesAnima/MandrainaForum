<?php
require_once 'db.php'; // Conecta con Neon

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // seguridad
    $nickname = $_POST['nickname'];
    $bio = $_POST['bio'] ?? '';

    // Manejo de imagen (si se sube)
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);
    }

    try {
        // Crear tabla si no existe (solo la primera vez)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id SERIAL PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                nickname TEXT NOT NULL,
                bio TEXT,
                profile_pic BYTEA
            )
        ");

        // Insertar usuario
        $sql = "INSERT INTO usuarios (email, password, nickname, bio, profile_pic)
                VALUES (:email, :password, :nickname, :bio, :profile_pic)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':profile_pic', $profile_pic, PDO::PARAM_LOB);
        $stmt->execute();

        echo "<h2 style='color:green;'>✅ Usuario registrado correctamente</h2>";
        echo "<a href='index principal.html'>Volver al login</a>";

    } catch (PDOException $e) {
        echo "<h2 style='color:red;'>❌ Error: " . $e->getMessage() . "</h2>";
    }
}
?>

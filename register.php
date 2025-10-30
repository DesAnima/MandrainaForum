<?php
ob_start(); // Inicia buffer de salida
require_once 'db.php'; // Conecta con Neon

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nickname = $_POST['nickname'];
    $bio = $_POST['bio'] ?? '';

    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);
    }

    try {
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

        $sql = "INSERT INTO usuarios (email, password, nickname, bio, profile_pic)
                VALUES (:email, :password, :nickname, :bio, :profile_pic)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':profile_pic', $profile_pic, PDO::PARAM_LOB);
        $stmt->execute();

        header("Location: index principal.html");
        exit();

    } catch (PDOException $e) {
        echo "<h2 style='color:red;'>❌ Error al registrar usuario: " . htmlspecialchars($e->getMessage()) . "</h2>";
    }
}

ob_end_flush(); // Vacía el buffer
?>

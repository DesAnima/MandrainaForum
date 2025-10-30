<?php
ob_start();
require_once 'db.php'; // Conecta con Neon (PostgreSQL)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nickname = trim($_POST['nickname']);
    $bio = $_POST['bio'] ?? '';

    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);
    }

    try {
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id SERIAL PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                nickname TEXT UNIQUE NOT NULL,
                bio TEXT,
                profile_pic BYTEA
            )
        ");

        // Verificar duplicados
        $checkSql = "SELECT email, nickname FROM usuarios WHERE email = :email OR nickname = :nickname";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':email' => $email, ':nickname' => $nickname]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['email'] === $email) {
                echo "<script>alert('❗ Este correo ya tiene una cuenta registrada.'); window.history.back();</script>";
                exit();
            } elseif ($existing['nickname'] === $nickname) {
                echo "<script>alert('⚠️ Este nombre de usuario ya está en uso.'); window.history.back();</script>";
                exit();
            }
        }

        // Insertar nuevo usuario si no hay duplicados
        $sql = "INSERT INTO usuarios (email, password, nickname, bio, profile_pic)
                VALUES (:email, :password, :nickname, :bio, :profile_pic)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':nickname', $nickname);
        $stmt->bindParam(':bio', $bio);
        $stmt->bindParam(':profile_pic', $profile_pic, PDO::PARAM_LOB);
        $stmt->execute();

        echo "<script>alert('✅ Registro exitoso. Ahora puedes iniciar sesión.'); window.location.href='index.html';</script>";
        exit();

    } catch (PDOException $e) {
        // Captura errores de SQL (por si hay duplicado pese a UNIQUE)
        if (strpos($e->getMessage(), 'duplicate key value') !== false) {
            echo "<script>alert('⚠️ El correo o el nombre de usuario ya están en uso.'); window.history.back();</script>";
        } else {
            echo "<h2 style='color:red;'>❌ Error al registrar usuario: " . htmlspecialchars($e->getMessage()) . "</h2>";
        }
    }
}

ob_end_flush();
?>

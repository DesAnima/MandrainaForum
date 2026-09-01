<?php
ob_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getPDO();

    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nickname = trim($_POST['nickname']);
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
                header("Location: register.html?error=email");
                exit();
            } elseif ($existing['nickname'] === $nickname) {
                header("Location: register.html?error=nickname");
                exit();
            }
        }

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

        header("Location: index.html?success=1");
        exit();

    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate key value') !== false) {
            header("Location: register.html?error=duplicate");
            exit();
        }
        echo "<h2 style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    }
}

ob_end_flush();

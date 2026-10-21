<?php
require_once 'db_connect.php';
require_once 'functions.php';

// Ficheiro: includes/service_reprova_process.php

secure_session_start();

if (!is_user_logged_in()) {
    header('Location: ../index.php?error=notloggedin');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $motivo = trim($_POST['motivo_reprova'] ?? '');

    if ($service_id && !empty($motivo)) {
        try {
            $sql = "UPDATE ordens_servico 
                    SET reprovado = 1, 
                        motivo_reprova = :motivo, 
                        data_reprova = NOW() 
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':motivo', $motivo);
            $stmt->bindParam(':id', $service_id);
            
            if ($stmt->execute()) {
                header("Location: ../pages/service_details.php?id=$service_id&success=reprova_adicionada");
                exit();
            } else {
                header("Location: ../pages/service_details.php?id=$service_id&error=db_error");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: ../pages/service_details.php?id=$service_id&error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
         header("Location: ../pages/service_details.php?id=" . ($service_id ?? '') . "&error=invalid_data");
         exit();
    }
} else {
    header("Location: ../pages/services_list.php");
    exit();
}

<?php
// actions/products/management.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    
    if ($action_type === 'add') {
        // Logika tambah data
    } else if ($action_type === 'update') {
        // Logika edit data
    } else if ($action_type === 'delete') {
        // Logika hapus data
    }
}
?>
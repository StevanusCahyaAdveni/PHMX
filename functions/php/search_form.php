<?php
/**
 * Fungsi Universal untuk membangun form pencarian
 * 
 * @param array $fields Konfigurasi input, contoh:
 * [
 *    ['name' => 'search_name', 'placeholder' => 'Cari Nama...', 'type' => 'text', 'col' => 'col-md-6'],
 *    ['name' => 'search_email', 'placeholder' => 'Cari Email...', 'type' => 'text', 'col' => 'col-md-6']
 * ]
 * @param array $btnConfig Konfigurasi tombol submit, contoh: ['text' => 'Cari', 'col' => 'col-2', 'class' => 'btn-primary']
 */
function generateSearchForm($fields = [], $btnConfig = [])
{
    $html = '<form action="" class="row g-2 align-items-center">';
    
    foreach ($fields as $field) {
        $name = $field['name'];
        $placeholder = $field['placeholder'] ?? 'Cari...';
        $type = $field['type'] ?? 'text';
        $col = $field['col'] ?? 'col-auto';
        $value = htmlspecialchars($_GET[$name] ?? '');
        
        $html .= "<div class='{$col}'>";
        $html .= "<input type='{$type}' name='{$name}' class='form-control form-control-sm' placeholder='{$placeholder}' value='{$value}'>";
        $html .= "</div>";
    }
    
    $btnText = $btnConfig['text'] ?? 'Cari';
    $btnCol = $btnConfig['col'] ?? 'col-auto';
    $btnClass = $btnConfig['class'] ?? 'btn btn-sm btn-outline-secondary w-100';
    
    $html .= "<div class='{$btnCol}'>";
    $html .= "<button type='submit' class='{$btnClass}'>{$btnText}</button>";
    $html .= '</div>';
    
    // Mempertahankan parameter lain seperti pagination jika ingin
    // Namun idealnya saat mencari, halaman kembali ke 1.
    // Jika ingin mempertahankan parameter lain selain 'page' dan input form, bisa ditambahkan hidden input di sini.
    
    $html .= '</form>';
    
    return $html;
}
?>

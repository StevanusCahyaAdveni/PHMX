<?php

/**
 * Upload File Handler with Compression
 * Support berbagai jenis file dengan kompresi otomatis
 */

/**
 * Upload file dengan handling kompresi berdasarkan tipe
 * 
 * @param array $file File dari $_FILES['field_name']
 * @param string $targetDir Direktori tujuan upload (default: uploads/)
 * @param int $maxSize Ukuran maksimal file dalam bytes (default: 5MB)
 * @param array $allowedTypes Array tipe file yang diizinkan (kosong = semua)
 * @return array ['success' => bool, 'message' => string, 'file_path' => string, 'file_name' => string]
 */
function uploadFile($file, $targetDir = 'uploads/', $maxSize = 5242880, $allowedTypes = [])
{
    // Cek apakah ada error saat upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => 'Upload error: ' . $file['error'],
            'file_path' => null,
            'file_name' => null
        ];
    }

    // Validasi ukuran file
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1024 / 1024, 2);
        return [
            'success' => false,
            'message' => "File terlalu besar! Maksimal {$maxSizeMB}MB",
            'file_path' => null,
            'file_name' => null
        ];
    }

    // Dapatkan informasi file
    $originalName = $file['name'];
    $tmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = mime_content_type($tmpPath);

    // Validasi tipe file jika ada batasan
    if (!empty($allowedTypes) && !in_array($fileExtension, $allowedTypes)) {
        return [
            'success' => false,
            'message' => 'Tipe file tidak diizinkan! Hanya: ' . implode(', ', $allowedTypes),
            'file_path' => null,
            'file_name' => null
        ];
    }

    // Generate nama file unik
    $uniqueName = generateUniqueFileName($originalName);

    // Buat direktori jika belum ada
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Path lengkap file tujuan
    $targetPath = $targetDir . $uniqueName;

    // Proses file berdasarkan tipe
    $fileType = getFileType($fileExtension, $mimeType);

    switch ($fileType) {
        case 'image':
            return uploadImage($tmpPath, $targetPath, $fileExtension);

        case 'pdf':
            return uploadPDF($tmpPath, $targetPath);

        case 'document':
        case 'other':
        default:
            return uploadGeneric($tmpPath, $targetPath);
    }
}

/**
 * Generate nama file unik
 * 
 * @param string $originalName Nama file asli
 * @return string Nama file unik
 */
function generateUniqueFileName($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);

    // Bersihkan nama file dari karakter aneh
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

    // Generate unique name: timestamp + random string + nama asli
    $uniqueId = date('YmdHis') . '_' . bin2hex(random_bytes(4));
    $uniqueName = $basename . '_' . $uniqueId . '.' . $extension;

    return $uniqueName;
}

/**
 * Deteksi tipe file
 * 
 * @param string $extension Ekstensi file
 * @param string $mimeType MIME type file
 * @return string Kategori file (image/pdf/document/other)
 */
function getFileType($extension, $mimeType)
{
    // Tipe gambar
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif', 'svg'];
    $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/heic', 'image/heif', 'image/svg+xml'];

    // Tipe PDF
    $pdfExtensions = ['pdf'];
    $pdfMimes = ['application/pdf'];

    // Tipe dokumen
    $documentExtensions = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt'];

    if (in_array($extension, $imageExtensions) || in_array($mimeType, $imageMimes)) {
        return 'image';
    }

    if (in_array($extension, $pdfExtensions) || in_array($mimeType, $pdfMimes)) {
        return 'pdf';
    }

    if (in_array($extension, $documentExtensions)) {
        return 'document';
    }

    return 'other';
}

/**
 * Upload dan kompres gambar
 * 
 * @param string $sourcePath Path file sumber
 * @param string $targetPath Path file tujuan
 * @param string $extension Ekstensi file
 * @return array Result upload
 */
function uploadImage($sourcePath, $targetPath, $extension)
{
    // Coba kompres gambar
    $compressed = false;

    // HEIC/HEIF perlu konversi ke JPEG (jika extension imagick tersedia)
    if (in_array($extension, ['heic', 'heif'])) {
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick($sourcePath);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(80);
                $targetPath = preg_replace('/\.(heic|heif)$/i', '.jpg', $targetPath);
                $imagick->writeImage($targetPath);
                $imagick->clear();
                $compressed = true;
            } catch (Exception $e) {
                // Gagal konversi, upload original
            }
        }
    }

    // Kompres gambar dengan GD
    if (!$compressed) {
        $imageInfo = getimagesize($sourcePath);

        if ($imageInfo !== false) {
            list($width, $height, $type) = $imageInfo;

            // Load gambar berdasarkan tipe
            $image = null;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($sourcePath);
                    }
                    break;
                case IMAGETYPE_BMP:
                    if (function_exists('imagecreatefrombmp')) {
                        $image = imagecreatefrombmp($sourcePath);
                    }
                    break;
            }

            // Jika berhasil load, kompres dan simpan
            if ($image !== null) {
                // Resize jika terlalu besar (max 2000px)
                $maxDimension = 2000;
                if ($width > $maxDimension || $height > $maxDimension) {
                    $ratio = min($maxDimension / $width, $maxDimension / $height);
                    $newWidth = round($width * $ratio);
                    $newHeight = round($height * $ratio);

                    $resized = imagecreatetruecolor($newWidth, $newHeight);

                    // Preserve transparency untuk PNG dan GIF
                    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                        imagealphablending($resized, false);
                        imagesavealpha($resized, true);
                        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                    }

                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resized;
                }

                // Simpan dengan kompresi
                $saved = false;
                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        $saved = imagejpeg($image, $targetPath, 80);
                        break;
                    case 'png':
                        $saved = imagepng($image, $targetPath, 6);
                        break;
                    case 'gif':
                        $saved = imagegif($image, $targetPath);
                        break;
                    case 'webp':
                        if (function_exists('imagewebp')) {
                            $saved = imagewebp($image, $targetPath, 80);
                        }
                        break;
                }

                imagedestroy($image);

                if ($saved) {
                    $compressed = true;
                }
            }
        }
    }

    // Jika gagal kompres, upload original
    if (!$compressed) {
        if (!move_uploaded_file($sourcePath, $targetPath)) {
            return [
                'success' => false,
                'message' => 'Gagal upload file',
                'file_path' => null,
                'file_name' => null
            ];
        }
    }

    return [
        'success' => true,
        'message' => 'Gambar berhasil diupload' . ($compressed ? ' dan dikompres' : ''),
        'file_path' => $targetPath,
        'file_name' => basename($targetPath)
    ];
}

/**
 * Upload PDF (dengan kompresi opsional)
 * 
 * @param string $sourcePath Path file sumber
 * @param string $targetPath Path file tujuan
 * @return array Result upload
 */
function uploadPDF($sourcePath, $targetPath)
{
    // PDF compression memerlukan library eksternal seperti Ghostscript
    // Untuk saat ini, langsung upload tanpa kompresi

    if (!move_uploaded_file($sourcePath, $targetPath)) {
        return [
            'success' => false,
            'message' => 'Gagal upload PDF',
            'file_path' => null,
            'file_name' => null
        ];
    }

    return [
        'success' => true,
        'message' => 'PDF berhasil diupload',
        'file_path' => $targetPath,
        'file_name' => basename($targetPath)
    ];
}

/**
 * Upload file generic (document, dll)
 * 
 * @param string $sourcePath Path file sumber
 * @param string $targetPath Path file tujuan
 * @return array Result upload
 */
function uploadGeneric($sourcePath, $targetPath)
{
    if (!move_uploaded_file($sourcePath, $targetPath)) {
        return [
            'success' => false,
            'message' => 'Gagal upload file',
            'file_path' => null,
            'file_name' => null
        ];
    }

    return [
        'success' => true,
        'message' => 'File berhasil diupload',
        'file_path' => $targetPath,
        'file_name' => basename($targetPath)
    ];
}

/**
 * ============================================
 * CONTOH PENGGUNAAN
 * ============================================
 */

/*
// 1. Upload gambar dengan kompresi otomatis
if (isset($_FILES['photo'])) {
    $result = uploadFile($_FILES['photo'], 'uploads/photos/', 5 * 1024 * 1024);
    
    if ($result['success']) {
        echo "Upload berhasil: " . $result['file_path'];
        // Simpan path ke database
        $photoPath = $result['file_path'];
    } else {
        echo "Upload gagal: " . $result['message'];
    }
}

// 2. Upload dengan batasan tipe file
if (isset($_FILES['document'])) {
    $allowedTypes = ['pdf', 'doc', 'docx'];
    $result = uploadFile(
        $_FILES['document'], 
        'uploads/documents/', 
        10 * 1024 * 1024, // 10MB
        $allowedTypes
    );
    
    if ($result['success']) {
        echo "File: " . $result['file_name'];
    }
}

// 3. Upload foto profil
if (isset($_FILES['profile_photo'])) {
    $result = uploadFile(
        $_FILES['profile_photo'], 
        'uploads/profiles/', 
        2 * 1024 * 1024, // 2MB
        ['jpg', 'jpeg', 'png', 'heic']
    );
    
    if ($result['success']) {
        // Update database
        $userId = $_SESSION['user_id'];
        executeSecure($con,
            "UPDATE users SET photo_profile = ? WHERE id = ?",
            [$result['file_path'], $userId],
            'ss'
        );
    }
}

// 4. Upload multiple files
if (isset($_FILES['attachments'])) {
    $files = $_FILES['attachments'];
    $uploadedFiles = [];
    
    for ($i = 0; $i < count($files['name']); $i++) {
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
        
        $result = uploadFile($file, 'uploads/attachments/');
        
        if ($result['success']) {
            $uploadedFiles[] = $result['file_path'];
        }
    }
    
    echo "Uploaded " . count($uploadedFiles) . " files";
}

// ============================================
// CATATAN PENTING:
// ============================================
// 1. Pastikan folder upload memiliki permission write (755)
// 2. Set max upload size di php.ini:
//    upload_max_filesize = 10M
//    post_max_size = 10M
// 3. Untuk HEIC, install imagick: pecl install imagick
// 4. Untuk kompresi PDF advanced, install Ghostscript
// 5. Selalu validasi dan sanitasi file upload
// ============================================
*/

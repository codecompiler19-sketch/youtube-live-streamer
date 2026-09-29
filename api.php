<?php
header('Content-Type: application/json');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);

$configFile = __DIR__ . '/config.json';
$playlistFile = __DIR__ . '/playlist.txt';
$logFile = __DIR__ . '/stream.log';
$pidFile = __DIR__ . '/pid.txt';

function getPythonBinary() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $localAppData = getenv('LOCALAPPDATA');
        $userProfile = getenv('USERPROFILE');
        $candidates = [
            "$localAppData\\Python\\pythoncore-3.14-64\\python.exe",
            "$localAppData\\Python\\bin\\python.exe",
            "$userProfile\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe",
            "$userProfile\\AppData\\Local\\Python\\bin\\python.exe",
            "$localAppData\\Programs\\Python\\Python312\\python.exe",
            "$localAppData\\Programs\\Python\\Python311\\python.exe",
            "$localAppData\\Programs\\Python\\Python310\\python.exe",
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe'
        ];
        foreach ($candidates as $cand) {
            if (!empty($cand) && file_exists($cand)) {
                return $cand;
            }
        }

        if (function_exists('shell_exec')) {
            $wherePath = @trim(shell_exec("where.exe python 2>NUL"));
            if (!empty($wherePath)) {
                $lines = explode("\n", str_replace("\r", "", $wherePath));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (file_exists($line) && strpos($line, 'WindowsApps') === false) {
                        return $line;
                    }
                }
                if (isset($lines[0]) && file_exists($lines[0])) {
                    return $lines[0];
                }
            }
        }

        return 'python';
    }
    return 'python3';
}

function loadConfig($configFile) {
    if (file_exists($configFile)) {
        return json_decode(file_get_contents($configFile), true) ?: [];
    }
    return [
        'stream_key' => '',
        'playlist_links' => '',
        'stream_mode' => 'local',
        'github_token' => '',
        'github_repo' => '',
        'channel_id' => ''
    ];
}

function saveConfig($configFile, $data) {
    file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
}

function isProcessRunning($pidFile) {
    if (!file_exists($pidFile)) {
        return false;
    }
    $pid = trim(file_get_contents($pidFile));
    if (empty($pid)) {
        return false;
    }
    if ($pid === 'cloud_active') {
        return true;
    }

    if (!function_exists('exec')) {
        return false;
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        if (is_numeric($pid)) {
            $output = [];
            @exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
            foreach ($output as $line) {
                if (strpos($line, (string)$pid) !== false) {
                    return true;
                }
            }
        }
        $wmicOutput = [];
        @exec('wmic process where "name like \'%python%\' and commandline like \'%stream.py%\'" get processid 2>NUL', $wmicOutput);
        foreach ($wmicOutput as $wline) {
            $wline = trim($wline);
            if (is_numeric($wline)) {
                return true;
            }
        }
    } else {
        if (is_numeric($pid)) {
            $output = [];
            @exec("ps -p $pid 2>&1", $output);
            if (count($output) > 1) {
                return true;
            }
        }
        $psOut = [];
        @exec("pgrep -f stream.py 2>&1", $psOut);
        if (!empty($psOut) && is_numeric(trim($psOut[0]))) {
            return true;
        }
    }
    return false;
}

function getGitHubRunStatus($githubRepo, $githubToken = '') {
    if (empty($githubRepo)) {
        return ['is_running' => false, 'status' => 'offline', 'conclusion' => null];
    }
    $url = "https://api.github.com/repos/" . trim($githubRepo, '/ ') . "/actions/runs?per_page=1";
    $ch = curl_init($url);
    $headers = [
        'User-Agent: PHP-Stream-Manager',
        'Accept: application/vnd.github.v3+json'
    ];
    if (!empty($githubToken)) {
        $headers[] = "Authorization: Bearer {$githubToken}";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $resp) {
        $data = json_decode($resp, true);
        if (!empty($data['workflow_runs'][0])) {
            $run = $data['workflow_runs'][0];
            $status = $run['status'] ?? 'unknown';
            $conclusion = $run['conclusion'] ?? null;
            $isLive = ($status === 'in_progress');
            return [
                'is_running' => $isLive,
                'status' => $status,
                'conclusion' => $conclusion,
                'run_url' => $run['html_url'] ?? '',
                'created_at' => $run['created_at'] ?? ''
            ];
        }
    }
    return ['is_running' => false, 'status' => 'offline', 'conclusion' => null];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_config':
        $config = loadConfig($configFile);
        $maskedConfig = $config;
        if (!empty($maskedConfig['stream_key'])) {
            $len = strlen($maskedConfig['stream_key']);
            if ($len > 6) {
                $maskedConfig['stream_key_masked'] = substr($maskedConfig['stream_key'], 0, 4) . '...' . substr($maskedConfig['stream_key'], -3);
            } else {
                $maskedConfig['stream_key_masked'] = '******';
            }
        } else {
            $maskedConfig['stream_key_masked'] = '';
        }
        echo json_encode(['status' => 'success', 'config' => $maskedConfig]);
        break;

    case 'save_config':
        $config = loadConfig($configFile);

        $streamKey = trim($_POST['stream_key'] ?? '');
        $playlistLinks = trim($_POST['playlist_links'] ?? '');
        $streamMode = $_POST['stream_mode'] ?? 'local';
        $githubToken = trim($_POST['github_token'] ?? '');
        $githubRepo = trim($_POST['github_repo'] ?? '');
        $channelId = trim($_POST['channel_id'] ?? '');

        if (!empty($streamKey)) {
            $config['stream_key'] = $streamKey;
        }
        if (isset($_POST['playlist_links'])) {
            $config['playlist_links'] = $playlistLinks;
            file_put_contents($playlistFile, $playlistLinks);
        }
        $config['stream_mode'] = $streamMode;
        $config['github_token'] = $githubToken;
        $config['github_repo'] = $githubRepo;
        $config['channel_id'] = $channelId;

        saveConfig($configFile, $config);

        echo json_encode(['status' => 'success', 'message' => 'Configuration saved successfully!']);
        break;

    case 'start_stream':
        $config = loadConfig($configFile);

        $streamKey = trim($_POST['stream_key'] ?? '');
        $playlistLinks = trim($_POST['playlist_links'] ?? '');
        $streamMode = $_POST['stream_mode'] ?? ($config['stream_mode'] ?? 'local');

        if (!empty($streamKey)) {
            $config['stream_key'] = $streamKey;
        } else {
            $streamKey = $config['stream_key'] ?? '';
        }

        if (isset($_POST['playlist_links']) && !empty($playlistLinks)) {
            $config['playlist_links'] = $playlistLinks;
            file_put_contents($playlistFile, $playlistLinks);
        }

        $config['stream_mode'] = $streamMode;
        if (isset($_POST['github_token'])) $config['github_token'] = trim($_POST['github_token']);
        if (isset($_POST['github_repo'])) $config['github_repo'] = trim($_POST['github_repo']);
        if (isset($_POST['channel_id'])) $config['channel_id'] = trim($_POST['channel_id']);

        saveConfig($configFile, $config);

        if (empty($streamKey)) {
            echo json_encode(['status' => 'error', 'message' => 'YouTube Stream Key is missing! Please enter your Stream Key in the field above.']);
            exit;
        }

        if ($streamMode === 'local') {
            if (!function_exists('proc_open')) {
                echo json_encode(['status' => 'error', 'message' => 'proc_open() is disabled by your web host. Shared hosting does not allow direct background processes. Please switch to "☁️ Cloud 24/7 Mode" to stream via GitHub Actions!']);
                exit;
            }

            if (isProcessRunning($pidFile)) {
                echo json_encode(['status' => 'error', 'message' => 'Stream is already running!']);
                exit;
            }

            if (!file_exists($playlistFile) || filesize($playlistFile) == 0) {
                echo json_encode(['status' => 'error', 'message' => 'Playlist is empty! Please add YouTube video links or a playlist link.']);
                exit;
            }

            $pythonScript = __DIR__ . '/stream.py';
            $logPath = __DIR__ . '/stream.log';

            file_put_contents($logPath, "=== Starting YouTube Live Stream at " . date('Y-m-d H:i:s') . " ===\n");

            $pythonBin = getPythonBinary();
            file_put_contents($logPath, "ℹ️ Using Python binary: $pythonBin\n", FILE_APPEND);

            // On Linux servers, ensure yt-dlp is installed for Python if exec is available
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && function_exists('exec')) {
                @exec("$pythonBin -m pip install --upgrade --break-system-packages yt-dlp --user 2>&1");
            }

            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["file", $logPath, "a"],
                2 => ["file", $logPath, "a"]
            ];
            
            $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
            $systemDrive = getenv('SystemDrive') ?: 'C:';
            $path = getenv('PATH') ?: '';
            $temp = getenv('TEMP') ?: 'C:\\Windows\\Temp';
            $tmp = getenv('TMP') ?: 'C:\\Windows\\Temp';

            $env = [
                'SystemRoot' => $systemRoot,
                'SystemDrive' => $systemDrive,
                'PATH' => $path,
                'TEMP' => $temp,
                'TMP' => $tmp,
                'YOUTUBE_STREAM_KEY' => $streamKey,
                'PYTHONUNBUFFERED' => '1',
                'PYTHONIOENCODING' => 'utf-8',
                'PLAYLIST_FILE' => $playlistFile
            ];

            $cmd = '"' . $pythonBin . '" ' . escapeshellarg($pythonScript);

            $process = proc_open($cmd, $descriptorspec, $pipes, __DIR__, $env);

            if (is_resource($process)) {
                $status = proc_get_status($process);
                $pid = $status['pid'];
                file_put_contents($pidFile, $pid);

                echo json_encode(['status' => 'success', 'message' => 'Local live stream process launched successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to launch Python streaming process on host. Check Python & FFmpeg installation.']);
            }
        } else {
            // Cloud GitHub Actions Dispatch Mode
            $githubToken = trim($config['github_token'] ?? '');
            $githubRepo = trim($config['github_repo'] ?? '', '/ ');

            if (empty($githubToken) || empty($githubRepo)) {
                echo json_encode(['status' => 'error', 'message' => 'GitHub Token and Repository (username/repo) are required for Cloud 24/7 mode!']);
                exit;
            }

            $url = "https://api.github.com/repos/{$githubRepo}/actions/workflows/stream.yml/dispatches";
            
            $dispatchFunc = function($refBranch) use ($url, $githubToken) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: PHP-Stream-Manager',
                    'Accept: application/vnd.github.v3+json',
                    "Authorization: Bearer {$githubToken}",
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ref' => $refBranch]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                return ['code' => $code, 'error' => $err, 'response' => $resp];
            };

            $res = $dispatchFunc('main');
            if ($res['code'] === 422 || $res['code'] === 404) {
                $resRetry = $dispatchFunc('master');
                if ($resRetry['code'] === 204) {
                    $res = $resRetry;
                }
            }

            $httpCode = $res['code'];

            if ($httpCode === 204) {
                file_put_contents($logFile, "=== Cloud 24/7 Stream Workflow Dispatched to GitHub Actions at " . date('Y-m-d H:i:s') . " ===\nCheck your GitHub Repository Actions tab for live cloud runner logs.\n");
                file_put_contents($pidFile, 'cloud_active');
                echo json_encode(['status' => 'success', 'message' => 'Cloud 24/7 Stream dispatched to GitHub Actions! Check your GitHub Actions tab to view live cloud logs.']);
            } else {
                $errorDetail = "";
                if ($httpCode === 401) {
                    $errorDetail = "GitHub Token (PAT) is invalid or expired.";
                } elseif ($httpCode === 403) {
                    $errorDetail = "GitHub Token lacks 'repo' / 'workflow' scope, or GitHub Actions is disabled in repo settings.";
                } elseif ($httpCode === 404) {
                    $errorDetail = "Repository '$githubRepo' or workflow '.github/workflows/stream.yml' was not found on GitHub. Make sure you pushed your files to GitHub!";
                } elseif ($httpCode === 422) {
                    $errorDetail = "GitHub branch ref error. Check that 'main' or 'master' branch exists in your repository.";
                } elseif ($httpCode === 0) {
                    $errorDetail = "Server network/SSL error: " . ($res['error'] ?: 'Could not reach api.github.com');
                } else {
                    $errorDetail = "HTTP $httpCode response from GitHub API.";
                }
                echo json_encode(['status' => 'error', 'message' => "GitHub dispatch failed: $errorDetail"]);
            }
        }
        break;

    case 'stop_stream':
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if (function_exists('exec')) {
                if (is_numeric($pid)) {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        @exec("taskkill /F /T /PID $pid 2>&1");
                        @exec('wmic process where "name like \'%python%\' and commandline like \'%stream.py%\'" call terminate 2>&1');
                    } else {
                        @exec("kill -9 $pid 2>&1");
                        @exec("pkill -f stream.py 2>&1");
                    }
                } else {
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        @exec('wmic process where "name like \'%python%\' and commandline like \'%stream.py%\'" call terminate 2>&1');
                    } else {
                        @exec("pkill -f stream.py 2>&1");
                    }
                }
            }
            @unlink($pidFile);
        } else {
            if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                @exec("pkill -f stream.py 2>&1");
            }
        }
        file_put_contents($logFile, "\n=== Stream Stopped at " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
        echo json_encode(['status' => 'success', 'message' => 'Stream stopped cleanly.']);
        break;

    case 'status':
        $config = loadConfig($configFile);
        $pidContent = file_exists($pidFile) ? trim(file_get_contents($pidFile)) : '';
        $isCloudMode = ($config['stream_mode'] ?? '') === 'cloud' || $pidContent === 'cloud_active';

        $cloudInfo = null;
        if ($isCloudMode && !empty($config['github_repo'])) {
            $cloudInfo = getGitHubRunStatus($config['github_repo'], $config['github_token'] ?? '');
            $isRunning = $cloudInfo['is_running'];
            $mode = 'cloud';
            if (!$isRunning && $pidContent === 'cloud_active' && ($cloudInfo['status'] === 'completed' || $cloudInfo['status'] === 'offline')) {
                // Clean up stale cloud pid if run ended
                @unlink($pidFile);
            }
        } else {
            $isRunning = isProcessRunning($pidFile);
            $mode = $isRunning ? 'local' : 'stopped';
        }

        $logText = file_exists($logFile) ? file_get_contents($logFile) : 'No logs yet.';
        $lines = explode("\n", $logText);
        if (count($lines) > 100) {
            $lines = array_slice($lines, -100);
        }
        $recentLogs = implode("\n", $lines);

        $playlistContent = file_exists($playlistFile) ? file_get_contents($playlistFile) : '';

        echo json_encode([
            'status' => 'success',
            'is_running' => $isRunning,
            'mode' => $mode,
            'cloud_info' => $cloudInfo,
            'pid' => $pidContent,
            'logs' => $recentLogs,
            'playlist' => $playlistContent
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

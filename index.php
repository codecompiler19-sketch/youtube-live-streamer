<?php
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <title>YouTube 24/7 Live Broadcaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-terminal::-webkit-scrollbar { width: 8px; }
        .log-terminal::-webkit-scrollbar-track { background: #0f172a; }
        .log-terminal::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        @keyframes pulse-glow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.08); }
        }
        .live-pulse { animation: pulse-glow 2s infinite ease-in-out; }
    </style>
</head>
<body class="h-full font-sans antialiased bg-slate-950 text-slate-100 flex flex-col">

    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="bg-red-600 p-2.5 rounded-xl shadow-lg shadow-red-900/40">
                    <i class="fa-brands fa-youtube text-2xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-tight">YouTube 24/7 Live Broadcaster</h1>
                    <p class="text-xs text-slate-400">Automated Continuous Live Streaming Engine for YouTube Channels</p>
                </div>
            </div>

            <!-- Header Live Status Badge -->
            <div id="statusBadge" class="flex items-center space-x-2 px-4 py-2 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700 transition-all">
                <span class="relative flex h-3 w-3">
                    <span id="statusPing" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-slate-400 opacity-75 hidden"></span>
                    <span id="statusDot" class="relative inline-flex rounded-full h-3 w-3 bg-slate-500"></span>
                </span>
                <span id="statusText">Checking Channel Status...</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        
        <!-- Alerts Banner -->
        <div id="alertBox" class="hidden p-4 rounded-xl text-sm font-medium flex items-center justify-between transition-all shadow-lg">
            <div class="flex items-center space-x-3">
                <i id="alertIcon" class="fa-solid text-lg"></i>
                <span id="alertMessage"></span>
            </div>
            <button onclick="dismissAlert()" class="text-current opacity-70 hover:opacity-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- HERO LIVE STATUS BANNER -->
        <div id="liveStatusHero" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl transition-all flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-5">
                <!-- Visual Live Icon -->
                <div id="heroIconBg" class="p-4 rounded-2xl bg-slate-800 text-slate-500 transition-all flex items-center justify-center">
                    <i id="heroIcon" class="fa-solid fa-circle-stop text-4xl"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span id="heroBadge" class="px-2.5 py-0.5 rounded-md text-xs font-bold uppercase tracking-wider bg-slate-800 text-slate-400">STREAM OFFLINE</span>
                        <span id="heroModeBadge" class="text-xs text-slate-400">Mode: Ready</span>
                    </div>
                    <h2 id="heroTitle" class="text-xl font-bold text-white mt-1">YouTube Channel Stream is Offline</h2>
                    <p id="heroDesc" class="text-xs text-slate-400 mt-0.5">Enter your YouTube Stream Key & Video/Playlist links below and click "Start Live Stream" to go live 24/7.</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div id="heroLivePulseBadge" class="hidden px-4 py-2.5 rounded-xl bg-emerald-950/90 border border-emerald-700 text-emerald-300 text-xs font-semibold flex items-center space-x-2 shadow-lg shadow-emerald-950/50">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <span>YOUTUBE CHANNEL IS LIVE 24/7</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Controls & Configuration -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Form Card -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                        <div class="flex items-center space-x-2 text-red-500 font-semibold text-base">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Stream Configuration</span>
                        </div>
                        <span class="text-xs text-slate-400">Auto-saved when starting</span>
                    </div>

                    <form id="configForm" onsubmit="saveConfiguration(event)" class="space-y-6">
                        
                        <!-- Stream Key -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                <i class="fa-solid fa-key text-red-500 mr-1.5"></i> YouTube Live Stream Key
                            </label>
                            <div class="relative">
                                <input type="password" id="streamKey" placeholder="e.g. xxxx-xxxx-xxxx-xxxx-xxxx"
                                    class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition font-mono">
                                <button type="button" onclick="togglePasswordVisibility('streamKey', 'keyEyeIcon')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white px-2">
                                    <i id="keyEyeIcon" class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-xs text-slate-500 mt-1.5">Get your Stream Key from <a href="https://studio.youtube.com/channel/live/livestreaming" target="_blank" class="text-red-400 hover:underline">YouTube Live Studio</a>.</p>
                        </div>

                        <!-- Playlist Links / Playlist URL -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                <i class="fa-solid fa-list-check text-red-500 mr-1.5"></i> YouTube Video Links OR Playlist URL
                            </label>
                            <textarea id="playlistLinks" rows="5" placeholder="Paste YouTube video URLs or Playlist URL here (one per line):&#10;https://www.youtube.com/watch?v=6F-oKeghrOg&#10;https://www.youtube.com/playlist?list=YOUR_PLAYLIST_ID"
                                class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition font-mono text-xs"></textarea>
                            <p class="text-xs text-slate-500 mt-1.5">Paste individual YouTube URLs OR a YouTube Playlist link. If 1 video link is provided, it loops continuously 24/7 on your YouTube channel.</p>
                        </div>

                        <!-- Stream Execution Mode -->
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-3">
                                <i class="fa-solid fa-server text-red-500 mr-1.5"></i> Streaming Engine Mode
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-start space-x-3 p-4 rounded-xl border border-slate-700 bg-slate-950/50 hover:border-slate-600 cursor-pointer transition">
                                    <input type="radio" name="stream_mode" value="local" checked onclick="toggleCloudSettings()" class="mt-1 text-red-600 focus:ring-red-500">
                                    <div>
                                        <span class="block text-sm font-semibold text-white">🖥️ Shared Server Direct Mode</span>
                                        <span class="block text-xs text-slate-400 mt-0.5">Runs Python/FFmpeg directly on your web host.</span>
                                    </div>
                                </label>
                                <label class="flex items-start space-x-3 p-4 rounded-xl border border-slate-700 bg-slate-950/50 hover:border-slate-600 cursor-pointer transition">
                                    <input type="radio" name="stream_mode" value="cloud" onclick="toggleCloudSettings()" class="mt-1 text-red-600 focus:ring-red-500">
                                    <div>
                                        <span class="block text-sm font-semibold text-white">☁️ Cloud 24/7 Mode (Recommended)</span>
                                        <span class="block text-xs text-slate-400 mt-0.5">Dispatches to free GitHub cloud runners. <b>Bypasses all shared hosting security limits!</b></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Cloud Settings (Hidden by default) -->
                        <div id="cloudSettings" class="hidden space-y-4 p-4 rounded-xl bg-slate-950 border border-slate-800">
                            <h4 class="text-xs font-bold text-red-400 uppercase tracking-wider flex items-center space-x-1.5">
                                <i class="fa-brands fa-github"></i>
                                <span>GitHub Cloud Setup (To Bypass Shared Host Binary Restrictions)</span>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">GitHub Token (PAT)</label>
                                    <input type="password" id="githubToken" placeholder="ghp_xxxxxxxxxxxx"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-red-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-400 mb-1">GitHub Repo (username/repository)</label>
                                    <input type="text" id="githubRepo" placeholder="yourusername/youtube-live-streamer"
                                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-red-500">
                                </div>
                            </div>
                            <p class="text-xs text-slate-500">Required so your PHP website can trigger GitHub's free cloud servers to stream 24/7 without hitting your shared hosting process limits.</p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium transition flex items-center space-x-2">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Save Settings</span>
                            </button>

                            <div class="flex items-center space-x-3">
                                <button type="button" onclick="startStream()" id="startBtn"
                                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white text-sm font-semibold shadow-lg shadow-red-900/30 transition flex items-center space-x-2">
                                    <i class="fa-solid fa-play"></i>
                                    <span id="startBtnText">Start Live Stream</span>
                                </button>
                                <button type="button" onclick="stopStream()" id="stopBtn"
                                    class="px-6 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-sm font-semibold transition flex items-center space-x-2">
                                    <i class="fa-solid fa-square"></i>
                                    <span>Stop Stream</span>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

            </div>

            <!-- Right Column: Live Console & Host Info -->
            <div class="space-y-6">

                <!-- Log Output Console -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-slate-300 font-semibold text-sm">
                            <i class="fa-solid fa-terminal text-red-500"></i>
                            <span>Live Output Console</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="fetchStatus()" class="text-xs text-slate-400 hover:text-white p-1" title="Refresh Logs">
                                <i class="fa-solid fa-rotate"></i>
                            </button>
                        </div>
                    </div>
                    <div id="logConsole" class="log-terminal h-80 bg-slate-950 border border-slate-800 rounded-xl p-4 font-mono text-xs text-emerald-400 overflow-y-auto whitespace-pre-wrap leading-relaxed">
Loading logs...
                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- JavaScript logic -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            fetchConfig();
            fetchStatus();
            setInterval(fetchStatus, 3000);
        });

        function togglePasswordVisibility(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                field.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        function toggleCloudSettings() {
            const modeRadio = document.querySelector('input[name="stream_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'local';
            const cloudSettings = document.getElementById("cloudSettings");
            if (mode === "cloud") {
                cloudSettings.classList.remove("hidden");
            } else {
                cloudSettings.classList.add("hidden");
            }
        }

        function showAlert(msg, type = "success") {
            const alertBox = document.getElementById("alertBox");
            const alertIcon = document.getElementById("alertIcon");
            const alertMessage = document.getElementById("alertMessage");

            alertBox.classList.remove("hidden", "bg-emerald-950", "text-emerald-300", "border-emerald-800", "bg-red-950", "text-red-300", "border-red-800");

            if (type === "success") {
                alertBox.classList.add("bg-emerald-950", "text-emerald-300", "border", "border-emerald-800");
                alertIcon.className = "fa-solid fa-circle-check text-emerald-400 text-lg";
            } else {
                alertBox.classList.add("bg-red-950", "text-red-300", "border", "border-red-800");
                alertIcon.className = "fa-solid fa-triangle-exclamation text-red-400 text-lg";
            }

            alertMessage.innerText = msg;
        }

        function dismissAlert() {
            document.getElementById("alertBox").classList.add("hidden");
        }

        async function fetchConfig() {
            try {
                const res = await fetch("api.php?action=get_config");
                const data = await res.json();
                if (data.status === "success" && data.config) {
                    const c = data.config;
                    if (c.stream_key) {
                        document.getElementById("streamKey").value = c.stream_key;
                    }
                    if (c.playlist_links) {
                        document.getElementById("playlistLinks").value = c.playlist_links;
                    }
                    if (c.stream_mode) {
                        const modeRadio = document.querySelector(`input[name="stream_mode"][value="${c.stream_mode}"]`);
                        if (modeRadio) modeRadio.checked = true;
                    }
                    if (c.github_token) {
                        document.getElementById("githubToken").value = c.github_token;
                    }
                    if (c.github_repo) {
                        document.getElementById("githubRepo").value = c.github_repo;
                    }
                    toggleCloudSettings();
                }
            } catch (err) {
                console.error("Config fetch error:", err);
            }
        }

        async function saveConfiguration(e) {
            if (e) e.preventDefault();
            const modeRadio = document.querySelector('input[name="stream_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'local';

            const formData = new FormData();
            formData.append("action", "save_config");
            formData.append("stream_key", document.getElementById("streamKey").value);
            formData.append("playlist_links", document.getElementById("playlistLinks").value);
            formData.append("stream_mode", mode);
            formData.append("github_token", document.getElementById("githubToken").value);
            formData.append("github_repo", document.getElementById("githubRepo").value);

            try {
                const res = await fetch("api.php", { method: "POST", body: formData });
                const data = await res.json();
                if (data.status === "success") {
                    showAlert(data.message, "success");
                } else {
                    showAlert(data.message, "error");
                }
            } catch (err) {
                showAlert("Error saving configuration", "error");
            }
        }

        async function startStream() {
            const modeRadio = document.querySelector('input[name="stream_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'local';

            const formData = new FormData();
            formData.append("action", "start_stream");
            formData.append("stream_key", document.getElementById("streamKey").value);
            formData.append("playlist_links", document.getElementById("playlistLinks").value);
            formData.append("stream_mode", mode);
            formData.append("github_token", document.getElementById("githubToken").value);
            formData.append("github_repo", document.getElementById("githubRepo").value);

            showAlert("Initiating live stream to YouTube channel...", "success");

            try {
                const res = await fetch("api.php", { method: "POST", body: formData });
                const data = await res.json();
                if (data.status === "success") {
                    showAlert(data.message, "success");
                    fetchStatus();
                } else {
                    showAlert(data.message, "error");
                }
            } catch (err) {
                showAlert("Error starting stream", "error");
            }
        }

        async function stopStream() {
            const formData = new FormData();
            formData.append("action", "stop_stream");

            try {
                const res = await fetch("api.php", { method: "POST", body: formData });
                const data = await res.json();
                if (data.status === "success") {
                    showAlert(data.message, "success");
                    fetchStatus();
                } else {
                    showAlert(data.message, "error");
                }
            } catch (err) {
                showAlert("Error stopping stream", "error");
            }
        }

        async function fetchStatus() {
            try {
                const res = await fetch("api.php?action=status");
                const data = await res.json();
                if (data.status === "success") {
                    const statusDot = document.getElementById("statusDot");
                    const statusPing = document.getElementById("statusPing");
                    const statusText = document.getElementById("statusText");
                    const statusBadge = document.getElementById("statusBadge");

                    const heroCard = document.getElementById("liveStatusHero");
                    const heroIconBg = document.getElementById("heroIconBg");
                    const heroIcon = document.getElementById("heroIcon");
                    const heroBadge = document.getElementById("heroBadge");
                    const heroModeBadge = document.getElementById("heroModeBadge");
                    const heroTitle = document.getElementById("heroTitle");
                    const heroDesc = document.getElementById("heroDesc");
                    const heroLivePulseBadge = document.getElementById("heroLivePulseBadge");
                    const startBtnText = document.getElementById("startBtnText");

                    if (data.is_running) {
                        // Header Status
                        statusBadge.className = "flex items-center space-x-2 px-4 py-2 rounded-full text-xs font-semibold bg-emerald-950 text-emerald-300 border border-emerald-700 shadow-md shadow-emerald-950/50";
                        statusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-400";
                        statusPing.classList.remove("hidden");
                        statusPing.className = "animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75";
                        statusText.innerText = data.mode === "cloud" ? "🟢 CLOUD 24/7 LIVE ACTIVE" : "🟢 SERVER LIVE ACTIVE";

                        // Hero Banner
                        heroCard.className = "bg-gradient-to-r from-emerald-950/90 via-slate-900 to-slate-900 border border-emerald-500/50 rounded-2xl p-6 shadow-2xl transition-all flex flex-col md:flex-row items-center justify-between gap-6";
                        heroIconBg.className = "p-4 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 live-pulse flex items-center justify-center";
                        heroIcon.className = "fa-brands fa-youtube text-4xl text-emerald-400";
                        
                        heroBadge.className = "px-2.5 py-0.5 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-500 text-slate-950 font-mono shadow-sm";
                        heroBadge.innerText = "🔴 YOUTUBE CHANNEL LIVE NOW";
                        heroModeBadge.innerText = data.mode === "cloud" ? "Mode: Cloud 24/7 (PC Off OK)" : "Mode: Shared Server";

                        heroTitle.innerText = "Your YouTube Channel is Live & Broadcasting Right Now!";
                        heroDesc.innerText = "Your videos are streaming live to YouTube 24/7 continuously. Anyone visiting your YouTube channel will see the live broadcast.";

                        heroLivePulseBadge.classList.remove("hidden");
                        startBtnText.innerText = "Stream Running";
                    } else {
                        // Header Status
                        statusBadge.className = "flex items-center space-x-2 px-4 py-2 rounded-full text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700";
                        statusDot.className = "relative inline-flex rounded-full h-3 w-3 bg-slate-500";
                        statusPing.classList.add("hidden");
                        statusText.innerText = "🔴 Channel Stream Stopped";

                        // Hero Banner
                        heroCard.className = "bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl transition-all flex flex-col md:flex-row items-center justify-between gap-6";
                        heroIconBg.className = "p-4 rounded-2xl bg-slate-800 text-slate-500 transition-all flex items-center justify-center";
                        heroIcon.className = "fa-solid fa-circle-stop text-4xl text-slate-500";

                        heroBadge.className = "px-2.5 py-0.5 rounded-md text-xs font-bold uppercase tracking-wider bg-slate-800 text-slate-400";
                        heroBadge.innerText = "STREAM OFFLINE";
                        heroModeBadge.innerText = "Mode: Ready";

                        heroTitle.innerText = "YouTube Channel Stream is Offline";
                        heroDesc.innerText = "Enter your YouTube Stream Key & Video/Playlist links below and click 'Start Live Stream' to go live 24/7.";

                        heroLivePulseBadge.classList.add("hidden");
                        startBtnText.innerText = "Start Live Stream";
                    }

                    // Update log console
                    const consoleDiv = document.getElementById("logConsole");
                    consoleDiv.innerText = data.logs || "No log activity yet.";
                    consoleDiv.scrollTop = consoleDiv.scrollHeight;
                }
            } catch (err) {
                console.error("Status fetch error:", err);
            }
        }
    </script>
</body>
</html>

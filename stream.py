#!/usr/bin/env python3
"""
YouTube Live Streamer
Streams videos from playlist.txt to YouTube Live via FFmpeg and yt-dlp.
Designed to run locally or on remote PHP servers without system dependencies.
"""

import os
import sys
import time
import subprocess
import json
import urllib.request

# Ensure UTF-8 output encoding for print statements to prevent Windows charmap encoding errors
if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass
if hasattr(sys.stderr, 'reconfigure'):
    try:
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass

# Maximum streaming duration in seconds (5 hours 55 minutes)
MAX_DURATION_SECONDS = int(os.getenv("MAX_STREAM_DURATION_SECONDS", "21300"))
PLAYLIST_FILE = os.getenv("PLAYLIST_FILE", "playlist.txt")


def get_yt_dlp_executable():
    """Ensure yt-dlp is available, auto-downloading standalone script if pip is missing."""
    try:
        import yt_dlp
        return ('module', yt_dlp)
    except ImportError:
        pass

    standalone_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "yt-dlp")
    if not os.path.exists(standalone_path) or os.path.getsize(standalone_path) < 10000:
        print("📦 'pip' is missing on server. Auto-downloading standalone yt-dlp binary...")
        try:
            url = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp"
            urllib.request.urlretrieve(url, standalone_path)
            os.chmod(standalone_path, 0o755)
            print("✅ Standalone yt-dlp downloaded successfully!")
        except Exception as e:
            print(f"⚠️ Standalone yt-dlp download failed: {e}")

    if os.path.exists(standalone_path):
        return ('standalone', standalone_path)

    return (None, None)


def get_ffmpeg_executable():
    """Locate or auto-download static FFmpeg binary on Linux if missing."""
    try:
        res = subprocess.run(["ffmpeg", "-version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if res.returncode == 0:
            return "ffmpeg"
    except Exception:
        pass

    common_paths = [
        "/usr/bin/ffmpeg",
        "/usr/local/bin/ffmpeg",
        "/opt/ffmpeg/bin/ffmpeg",
        "/usr/local/ffmpeg/bin/ffmpeg"
    ]
    for path in common_paths:
        if os.path.exists(path):
            return path

    local_ffmpeg = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ffmpeg")
    if os.path.exists(local_ffmpeg):
        os.chmod(local_ffmpeg, 0o755)
        return local_ffmpeg

    print("📦 FFmpeg binary not found on server. Auto-downloading static FFmpeg build...")
    try:
        tar_url = "https://johnvansickle.com/ffmpeg/builds/ffmpeg-git-amd64-static.tar.xz"
        tar_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "ffmpeg-static.tar.xz")
        urllib.request.urlretrieve(tar_url, tar_path)
        
        cmd = f"tar -xf {tar_path} --wildcards '*/ffmpeg' --strip-components=1 -C {os.path.dirname(os.path.abspath(__file__))}"
        subprocess.run(cmd, shell=True)
        if os.path.exists(local_ffmpeg):
            os.chmod(local_ffmpeg, 0o755)
            print("✅ Static FFmpeg binary downloaded and extracted successfully!")
            return local_ffmpeg
    except Exception as e:
        print(f"⚠️ Static FFmpeg download failed: {e}")

    return "ffmpeg"


def get_stream_target():
    """Retrieve and validate YouTube Stream Key and Server URL from environment."""
    stream_key = os.getenv("YOUTUBE_STREAM_KEY", "").strip().strip('"').strip("'").strip()
    if not stream_key:
        print("❌ ERROR: YOUTUBE_STREAM_KEY environment variable is missing or empty.")
        sys.exit(1)

    print(f"🔑 Stream key loaded successfully (length: {len(stream_key)} chars).")
    rtmps_url = os.getenv("YOUTUBE_RTMPS_URL", "rtmp://a.rtmp.youtube.com/live2").strip().rstrip("/")
    return f"{rtmps_url}/{stream_key}"


def expand_playlist_item(item):
    """If item is a YouTube playlist URL, expand it into individual video URLs."""
    if ("list=" in item or "playlist" in item) and item.startswith(("http://", "https://")):
        print(f"📋 YouTube playlist URL detected: {item}. Extracting entries...")
        yt_type, yt_obj = get_yt_dlp_executable()
        if yt_type == 'module':
            try:
                ydl_opts = {'extract_flat': True, 'quiet': True, 'skip_download': True}
                with yt_obj.YoutubeDL(ydl_opts) as ydl:
                    info = ydl.extract_info(item, download=False)
                    entries = info.get('entries', [])
                    urls = []
                    for entry in entries:
                        v_id = entry.get('id') or entry.get('url')
                        if v_id:
                            if not v_id.startswith('http'):
                                v_id = f"https://www.youtube.com/watch?v={v_id}"
                            urls.append(v_id)
                    if urls:
                        print(f"✅ Extracted {len(urls)} videos from playlist.")
                        return urls
            except Exception as e:
                print(f"⚠️ Playlist extraction error: {e}")
        elif yt_type == 'standalone':
            try:
                cmd = [sys.executable, yt_obj, "--flat-playlist", "-j", item]
                res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=30)
                if res.returncode == 0 and res.stdout:
                    urls = []
                    for line in res.stdout.strip().split("\n"):
                        if line.strip():
                            try:
                                entry = json.loads(line)
                                v_id = entry.get('id') or entry.get('url')
                                if v_id:
                                    if not v_id.startswith('http'):
                                        v_id = f"https://www.youtube.com/watch?v={v_id}"
                                    urls.append(v_id)
                            except Exception:
                                pass
                    if urls:
                        print(f"✅ Extracted {len(urls)} videos from playlist via standalone yt-dlp.")
                        return urls
            except Exception as e:
                print(f"⚠️ Standalone playlist extraction failed: {e}")
    return [item]


def load_playlist(filename):
    """Load video URLs from playlist text file."""
    if not os.path.exists(filename):
        print(f"❌ ERROR: Playlist file '{filename}' not found.")
        sys.exit(1)

    raw_items = []
    with open(filename, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith("#"):
                raw_items.append(line)

    if not raw_items:
        print(f"❌ ERROR: Playlist file '{filename}' contains no valid URLs.")
        sys.exit(1)

    expanded_urls = []
    for item in raw_items:
        expanded_urls.extend(expand_playlist_item(item))

    return expanded_urls


def extract_media_urls(youtube_url):
    """Extract direct media stream URLs using yt-dlp (module or standalone) or local MP4 file."""
    # Check if local video file or direct video stream URL
    if os.path.exists(youtube_url) or (youtube_url.startswith(('http://', 'https://')) and any(youtube_url.lower().endswith(ext) for ext in ['.mp4', '.mkv', '.avi', '.mov', '.flv', '.ts'])):
        print(f"\n🎥 Direct video file/URL detected: {youtube_url}")
        return {
            'type': 'single',
            'url': youtube_url,
            'user_agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        }

    print(f"\n🔍 Extracting media stream for: {youtube_url}")
    
    yt_type, yt_obj = get_yt_dlp_executable()
    if not yt_type:
        print("❌ ERROR: Could not initialize yt-dlp. Make sure python3 can access internet to download standalone yt-dlp.")
        return None

    if yt_type == 'module':
        cookie_file = os.getenv("YOUTUBE_COOKIE_FILE")
        client_strategies = [
            {'player_client': ['tv', 'visionos', 'mweb']},
            {'player_client': ['android', 'ios']},
            {'player_client': ['tv'], 'player_skip': ['web']},
            {'player_client': ['mweb', 'ios']}
        ]

        for idx, strategy in enumerate(client_strategies, 1):
            ydl_opts = {
                'format': 'b/best/bestvideo+bestaudio',
                'quiet': True,
                'no_warnings': True,
                'noplaylist': True,
                'extractor_args': {'youtube': strategy}
            }
            if cookie_file and os.path.exists(cookie_file):
                ydl_opts['cookiefile'] = cookie_file

            try:
                with yt_obj.YoutubeDL(ydl_opts) as ydl:
                    info = ydl.extract_info(youtube_url, download=False)
                    headers = info.get('http_headers', {})
                    user_agent = headers.get('User-Agent', '')

                    if 'requested_formats' in info and len(info['requested_formats']) >= 2:
                        video_url, audio_url = None, None
                        v_ua, a_ua = user_agent, user_agent
                        for fmt in info['requested_formats']:
                            if fmt.get('vcodec') != 'none' and not video_url:
                                video_url = fmt.get('url')
                                v_ua = fmt.get('http_headers', {}).get('User-Agent', user_agent)
                            elif fmt.get('acodec') != 'none' and not audio_url:
                                audio_url = fmt.get('url')
                                a_ua = fmt.get('http_headers', {}).get('User-Agent', user_agent)
                        
                        if video_url and audio_url:
                            print(f"✅ Extracted stream URLs using Strategy {idx}.")
                            return {
                                'type': 'dual',
                                'video_url': video_url,
                                'audio_url': audio_url,
                                'video_ua': v_ua,
                                'audio_ua': a_ua
                            }

                    if 'url' in info:
                        print(f"✅ Extracted single stream URL using Strategy {idx}.")
                        return {
                            'type': 'single',
                            'url': info['url'],
                            'user_agent': user_agent
                        }
            except Exception as e:
                pass
    else:
        # Standalone yt-dlp executable CLI fallback
        print("ℹ️ Using standalone yt-dlp CLI for stream extraction...")
        try:
            cmd = [sys.executable, yt_obj, "-j", "--no-playlist", "--extractor-args", "youtube:player_client=tv,mweb,ios,android;player_skip=web,web_creator", "-f", "b/best/bestvideo+bestaudio", youtube_url]
            res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=45)
            if res.returncode == 0 and res.stdout:
                info = json.loads(res.stdout)
                
                # Check for dual requested formats
                if 'requested_formats' in info and len(info['requested_formats']) >= 2:
                    v_url, a_url = None, None
                    for fmt in info['requested_formats']:
                        if fmt.get('vcodec') != 'none' and not v_url:
                            v_url = fmt.get('url')
                        elif fmt.get('acodec') != 'none' and not a_url:
                            a_url = fmt.get('url')
                    if v_url and a_url:
                        print("✅ Extracted dual stream URLs via standalone yt-dlp CLI.")
                        return {
                            'type': 'dual',
                            'video_url': v_url,
                            'audio_url': a_url,
                            'video_ua': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                            'audio_ua': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                        }

                url = info.get('url')
                if not url and 'formats' in info:
                    for fmt in reversed(info['formats']):
                        if fmt.get('url') and fmt.get('vcodec') != 'none':
                            url = fmt.get('url')
                            break

                if url:
                    print("✅ Extracted single stream URL via standalone yt-dlp CLI.")
                    return {
                        'type': 'single',
                        'url': url,
                        'user_agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
                    }
            else:
                print(f"⚠️ Standalone yt-dlp error output: {res.stderr.strip()[:200]}")
        except Exception as e:
            print(f"⚠️ Standalone CLI extraction exception: {e}")

    print(f"❌ Could not extract stream URL for {youtube_url}. Verify video is public and accessible.")
    return None


def stream_video(media_data, stream_target):
    """Stream media via FFmpeg to YouTube Live."""
    ffmpeg_bin = get_ffmpeg_executable()
    targets_to_try = [stream_target]
    if "rtmp://" in stream_target:
        rtmps_target = stream_target.replace("rtmp://a.rtmp.youtube.com/live2", "rtmps://a.rtmp.youtube.com/live2:443")
        if rtmps_target not in targets_to_try:
            targets_to_try.append(rtmps_target)

    reconnect_flags = [
        "-reconnect", "1",
        "-reconnect_at_eof", "1",
        "-reconnect_streamed", "1",
        "-reconnect_delay_max", "5"
    ]

    for idx, target in enumerate(targets_to_try, 1):
        # Configuration variants: Strategy 1 = Stream Copy (0% CPU, minimal RAM for shared hosting)
        # Strategy 2 = Ultrafast 1-thread fallback
        for strat in [1, 2]:
            cmd = [ffmpeg_bin, "-hide_banner", "-loglevel", "info"]

            if media_data['type'] == 'dual':
                if media_data.get('video_ua'):
                    cmd.extend(["-user_agent", media_data['video_ua']])
                cmd.extend(reconnect_flags)
                cmd.extend(["-re", "-i", media_data['video_url']])

                if media_data.get('audio_ua'):
                    cmd.extend(["-user_agent", media_data['audio_ua']])
                cmd.extend(reconnect_flags)
                cmd.extend(["-re", "-i", media_data['audio_url']])

                cmd.extend(["-map", "0:v:0", "-map", "1:a:0"])
            else:
                s_url = media_data['url']
                ua = media_data.get('user_agent', '')
                is_http = s_url.startswith(('http://', 'https://'))

                if is_http:
                    if ua:
                        cmd.extend(["-user_agent", ua])
                    cmd.extend(reconnect_flags)
                    cmd.extend(["-re", "-i", s_url])
                else:
                    cmd.extend(["-stream_loop", "-1", "-re", "-i", s_url])

            cmd.extend(["-threads", "1"])

            if strat == 1:
                # Strategy 1: Ultrafast libx264 encoding for guaranteed YouTube RTMP FLV compatibility
                cmd.extend([
                    "-c:v", "libx264",
                    "-preset", "ultrafast",
                    "-b:v", "2500k",
                    "-maxrate", "2500k",
                    "-bufsize", "5000k",
                    "-pix_fmt", "yuv420p",
                    "-g", "60",
                    "-c:a", "aac",
                    "-b:a", "128k",
                    "-ar", "44100",
                    "-f", "flv",
                    target
                ])
            else:
                # Strategy 2: Stream copy fallback
                cmd.extend([
                    "-c:v", "copy",
                    "-c:a", "aac",
                    "-b:a", "128k",
                    "-ar", "44100",
                    "-f", "flv",
                    target
                ])

            print(f"▶️ Executing FFmpeg stream (Target {idx}, Strategy {strat}) using '{ffmpeg_bin}'...")
            try:
                process = subprocess.Popen(cmd)
                process.wait()
                if process.returncode == 0:
                    return True
                print(f"⚠️ FFmpeg stream strategy {strat} exited with code {process.returncode}.")
            except Exception as e:
                print(f"⚠️ FFmpeg process error: {e}")

    return False


def main():
    stream_target = get_stream_target()
    playlist = load_playlist(PLAYLIST_FILE)

    print("==========================================")
    print("🚀 YouTube 24/7 Live Streamer Started")
    print(f"📋 Loaded {len(playlist)} video(s) into loop.")
    print("==========================================")

    start_time = time.time()
    idx = 0
    failed_attempts = 0

    while True:
        elapsed = time.time() - start_time
        if elapsed >= MAX_DURATION_SECONDS:
            print(f"\n⏰ Maximum duration reached ({MAX_DURATION_SECONDS}s). Restarting cycle cleanly...")
            start_time = time.time()

        current_url = playlist[idx % len(playlist)]
        print(f"\n[{idx + 1}] Processing item {idx % len(playlist) + 1}/{len(playlist)}: {current_url}")

        media_data = extract_media_urls(current_url)
        if media_data:
            failed_attempts = 0
            success = stream_video(media_data, stream_target)
            if not success:
                print("⚠️ Stream finished. Retrying next item in 5 seconds...")
                time.sleep(5)
        else:
            failed_attempts += 1
            print(f"⚠️ Extraction failed (Attempt {failed_attempts}).")
            if failed_attempts >= 5:
                print("❌ ERROR: 5 failed attempts in a row. Check that your video links are public and valid.")
                time.sleep(10)
                failed_attempts = 0
            time.sleep(5)

        idx += 1


if __name__ == "__main__":
    main()

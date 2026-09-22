#!/usr/bin/env python3
"""
YouTube Live Streamer
Streams videos from playlist.txt to YouTube Live via FFmpeg and yt-dlp.
Designed to run on GitHub Actions standard runners.
"""

import os
import sys
import time
import subprocess
import yt_dlp

# Maximum streaming duration in seconds (5 hours 55 minutes to stay within GitHub Actions 6h limit)
MAX_DURATION_SECONDS = int(os.getenv("MAX_STREAM_DURATION_SECONDS", "21300"))
PLAYLIST_FILE = os.getenv("PLAYLIST_FILE", "playlist.txt")


def get_stream_target():
    """Retrieve and validate YouTube Stream Key and Server URL from environment."""
    stream_key = os.getenv("YOUTUBE_STREAM_KEY", "").strip()
    if not stream_key:
        print("❌ ERROR: YOUTUBE_STREAM_KEY environment variable is missing or empty.")
        print("Please configure YOUTUBE_STREAM_KEY in GitHub Repository Settings -> Secrets and variables -> Actions.")
        sys.exit(1)

    print(f"🔑 Stream key loaded successfully (length: {len(stream_key)} chars).")
    rtmps_url = os.getenv("YOUTUBE_RTMPS_URL", "rtmp://a.rtmp.youtube.com/live2").strip().rstrip("/")
    return f"{rtmps_url}/{stream_key}"


def load_playlist(filename):
    """Load video URLs from playlist text file."""
    if not os.path.exists(filename):
        print(f"❌ ERROR: Playlist file '{filename}' not found.")
        sys.exit(1)

    urls = []
    with open(filename, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith("#"):
                urls.append(line)

    if not urls:
        print(f"❌ ERROR: Playlist file '{filename}' contains no valid URLs.")
        sys.exit(1)

    return urls


def extract_media_urls(youtube_url):
    """Extract direct media stream URLs using TV/iOS client (no cookies) with cookie fallback."""
    print(f"\n🔍 Extracting media stream for: {youtube_url}")
    
    # Strategy 1: TV/iOS/Android_VR clients WITHOUT cookies (bypasses bot verification completely)
    tv_opts = {
        'format': 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
        'quiet': False,
        'no_warnings': False,
        'noplaylist': True,
        'extractor_args': {
            'youtube': {
                'player_client': ['tv', 'android_vr', 'ios']
            }
        }
    }

    try:
        with yt_dlp.YoutubeDL(tv_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=False)
            
            if 'requested_formats' in info and len(info['requested_formats']) >= 2:
                v_url, a_url = None, None
                for fmt in info['requested_formats']:
                    if fmt.get('vcodec') != 'none' and not v_url:
                        v_url = fmt.get('url')
                    elif fmt.get('acodec') != 'none' and not a_url:
                        a_url = fmt.get('url')
                if v_url and a_url:
                    print("✅ Extracted dual video + audio stream URLs (TV client).")
                    return [v_url, a_url]

            if 'url' in info:
                print("✅ Extracted stream URL (TV client).")
                return [info['url']]
    except Exception as e:
        print(f"⚠️ TV client extraction failed: {e}")

    # Strategy 2: Cookie-based fallback if TV client fails
    cookie_file = os.getenv("YOUTUBE_COOKIE_FILE")
    if cookie_file and os.path.exists(cookie_file):
        print(f"🍪 Retrying with YouTube cookie file: {cookie_file}")
        cookie_opts = {
            'format': 'best',
            'noplaylist': True,
            'cookiefile': cookie_file
        }
        try:
            with yt_dlp.YoutubeDL(cookie_opts) as ydl:
                info = ydl.extract_info(youtube_url, download=False)
                if 'url' in info:
                    print("✅ Extracted stream URL (Cookie fallback).")
                    return [info['url']]
        except Exception as e2:
            print(f"❌ Cookie extraction failed: {e2}")

    return None


def stream_video(media_urls, stream_target):
    """Stream media via FFmpeg to YouTube Live."""
    ffmpeg_cmd = ["ffmpeg", "-hide_banner", "-loglevel", "info"]

    reconnect_flags = [
        "-reconnect", "1",
        "-reconnect_at_eof", "1",
        "-reconnect_streamed", "1",
        "-reconnect_delay_max", "5"
    ]

    if len(media_urls) == 2:
        v_url, a_url = media_urls
        ffmpeg_cmd.extend(reconnect_flags)
        ffmpeg_cmd.extend(["-re", "-i", v_url])
        ffmpeg_cmd.extend(reconnect_flags)
        ffmpeg_cmd.extend(["-re", "-i", a_url])
        ffmpeg_cmd.extend(["-map", "0:v:0", "-map", "1:a:0"])
    else:
        s_url = media_urls[0]
        ffmpeg_cmd.extend(reconnect_flags)
        ffmpeg_cmd.extend(["-re", "-i", s_url])

    # Standard YouTube Live H.264 + AAC output configuration
    ffmpeg_cmd.extend([
        "-c:v", "libx264",
        "-preset", "veryfast",
        "-tune", "zerolatency",
        "-b:v", "4500k",
        "-maxrate", "4500k",
        "-bufsize", "9000k",
        "-pix_fmt", "yuv420p",
        "-g", "60",
        "-keyint_min", "60",
        "-c:a", "aac",
        "-b:a", "128k",
        "-ar", "44100",
        "-f", "flv",
        stream_target
    ])

    print("▶️ Executing FFmpeg command...")
    try:
        process = subprocess.Popen(ffmpeg_cmd)
        process.wait()
        return process.returncode == 0
    except Exception as e:
        print(f"⚠️ FFmpeg process error: {e}")
        return False


def main():
    stream_target = get_stream_target()
    playlist = load_playlist(PLAYLIST_FILE)

    print("==========================================")
    print("🚀 YouTube Live Streamer Started")
    print(f"📋 Loaded {len(playlist)} video(s) into loop.")
    print(f"⏱️ Maximum duration set to {MAX_DURATION_SECONDS // 3600}h {(MAX_DURATION_SECONDS % 3600) // 60}m.")
    print("==========================================")

    start_time = time.time()
    idx = 0
    failed_attempts = 0

    while True:
        elapsed = time.time() - start_time
        if elapsed >= MAX_DURATION_SECONDS:
            print(f"\n⏰ Maximum duration of {MAX_DURATION_SECONDS}s reached. Stopping stream cleanly.")
            break

        current_url = playlist[idx % len(playlist)]
        print(f"\n[{idx + 1}] Processing item {idx % len(playlist) + 1}/{len(playlist)}: {current_url}")

        media_urls = extract_media_urls(current_url)
        if media_urls:
            failed_attempts = 0
            success = stream_video(media_urls, stream_target)
            if not success:
                print("⚠️ Stream finished with warnings/errors. Waiting 5 seconds before next item...")
                time.sleep(5)
        else:
            failed_attempts += 1
            print(f"⚠️ Extraction failed (Attempt {failed_attempts}).")
            if failed_attempts >= 5:
                print("❌ ERROR: Failed to extract stream URLs 5 times in a row. Check playlist URLs and yt-dlp compatibility.")
                sys.exit(1)
            time.sleep(5)

        idx += 1


if __name__ == "__main__":
    main()

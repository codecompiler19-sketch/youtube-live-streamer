#!/usr/bin/env python3
"""
YouTube Live Streamer
Streams videos from playlist.txt to YouTube Live via FFmpeg and yt-dlp.
Designed to run locally or on GitHub Actions runners.
"""

import os
import sys
import time
import subprocess
import yt_dlp

# Maximum streaming duration in seconds (5 hours 55 minutes)
MAX_DURATION_SECONDS = int(os.getenv("MAX_STREAM_DURATION_SECONDS", "21300"))
PLAYLIST_FILE = os.getenv("PLAYLIST_FILE", "playlist.txt")


def get_stream_target():
    """Retrieve and validate YouTube Stream Key and Server URL from environment."""
    stream_key = os.getenv("YOUTUBE_STREAM_KEY", "").strip()
    if not stream_key:
        print("❌ ERROR: YOUTUBE_STREAM_KEY environment variable is missing or empty.")
        print("Please set YOUTUBE_STREAM_KEY in your terminal (e.g. set YOUTUBE_STREAM_KEY=your_key).")
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
    """Extract direct media stream URLs and HTTP headers using yt-dlp."""
    print(f"\n🔍 Extracting media stream for: {youtube_url}")
    
    ydl_opts = {
        'format': 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
        'quiet': False,
        'no_warnings': False,
        'noplaylist': True,
        'js_runtimes': {'node': {}},
        'extractor_args': {
            'youtube': {
                'player_client': ['android', 'ios', 'tv', 'mweb']
            }
        }
    }

    cookie_file = os.getenv("YOUTUBE_COOKIE_FILE")
    if cookie_file and os.path.exists(cookie_file):
        print(f"🍪 Using YouTube cookies from: {cookie_file}")
        ydl_opts['cookiefile'] = cookie_file

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(youtube_url, download=False)
            
            headers = info.get('http_headers', {})
            user_agent = headers.get('User-Agent', '')

            # Check for split video and audio streams
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
                    print("✅ Extracted dual video + audio stream URLs successfully.")
                    return {
                        'type': 'dual',
                        'video_url': video_url,
                        'audio_url': audio_url,
                        'video_ua': v_ua,
                        'audio_ua': a_ua
                    }

            # Single combined stream fallback
            if 'url' in info:
                print("✅ Extracted single combined stream URL successfully.")
                return {
                    'type': 'single',
                    'url': info['url'],
                    'user_agent': user_agent
                }

            raise ValueError("Could not extract stream URL from video format metadata.")
    except Exception as e:
        print(f"❌ Extraction error for {youtube_url}: {e}")
        return None


def stream_video(media_data, stream_target):
    """Stream media via FFmpeg to YouTube Live passing required User-Agent headers."""
    ffmpeg_cmd = ["ffmpeg", "-hide_banner", "-loglevel", "info"]

    reconnect_flags = [
        "-reconnect", "1",
        "-reconnect_at_eof", "1",
        "-reconnect_streamed", "1",
        "-reconnect_delay_max", "5"
    ]

    if media_data['type'] == 'dual':
        v_url = media_data['video_url']
        a_url = media_data['audio_url']
        v_ua = media_data['video_ua']
        a_ua = media_data['audio_ua']

        ffmpeg_cmd.extend(["-user_agent", v_ua])
        ffmpeg_cmd.extend(reconnect_flags)
        ffmpeg_cmd.extend(["-re", "-i", v_url])

        ffmpeg_cmd.extend(["-user_agent", a_ua])
        ffmpeg_cmd.extend(reconnect_flags)
        ffmpeg_cmd.extend(["-re", "-i", a_url])

        ffmpeg_cmd.extend(["-map", "0:v:0", "-map", "1:a:0"])
    else:
        s_url = media_data['url']
        ua = media_data['user_agent']

        ffmpeg_cmd.extend(["-user_agent", ua])
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

    print("▶️ Executing FFmpeg stream output to YouTube Live...")
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

        media_data = extract_media_urls(current_url)
        if media_data:
            failed_attempts = 0
            success = stream_video(media_data, stream_target)
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

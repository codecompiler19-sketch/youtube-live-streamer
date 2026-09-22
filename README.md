# 🚀 YouTube Live Streamer

A lightweight, automated Python live streaming tool that continuously broadcasts YouTube videos from `playlist.txt` to YouTube Live using FFmpeg and `yt-dlp`.

---

## 📋 Prerequisites

Before running on any computer, make sure you have installed:
1. **Python 3.10+**: [Download Python for Windows/Mac/Linux](https://www.python.org/downloads/)
2. **Node.js**: [Download Node.js Official Installer](https://nodejs.org/en/download/)
3. **FFmpeg**: [Download FFmpeg Official Builds](https://ffmpeg.org/download.html) *(Windows users can also use [Gyan.dev FFmpeg Builds](https://www.gyan.dev/ffmpeg/builds/))*

---

## ⚡ Quick Setup & Usage (3 Steps)

### Step 1: Install Python Dependencies
Open Command Prompt / Terminal and run:
```bash
pip install yt-dlp
```

### Step 2: Configure `playlist.txt`
Add your YouTube video URLs into `playlist.txt` (one URL per line):
```text
https://www.youtube.com/watch?v=6F-oKeghrOg
```
*(If only 1 video URL is added, that single video will loop continuously 24/7).*

### Step 3: Run the Stream
Set your YouTube Stream Key and start streaming:

**On Windows (Command Prompt):**
```cmd
set YOUTUBE_STREAM_KEY=your_youtube_stream_key_here
python stream.py
```

**On Mac / Linux:**
```bash
export YOUTUBE_STREAM_KEY="your_youtube_stream_key_here"
python stream.py
```

---

## 🛑 How to Stop or Change Videos

- **To Stop**: Press `Ctrl + C` in your Command Prompt / Terminal window.
- **To Change Videos**: Edit `playlist.txt` with new YouTube links. If the stream is running, it will automatically play the updated links when the current video completes.

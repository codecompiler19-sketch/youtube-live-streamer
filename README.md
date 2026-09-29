# 🚀 YouTube 24/7 Live Streamer Studio & WordPress Integration

A full-stack PHP web application (Frontend + Backend) and Python automation engine to broadcast YouTube video links & playlists continuously 24/7 to YouTube Live and display them live on your WordPress website.

---

## 🌟 Key Features

1. **Modern PHP Web Dashboard (`index.php`)**:
   - Enter your **YouTube Stream Key**.
   - Input **individual YouTube video URLs** or **YouTube Playlist URLs** (`https://www.youtube.com/playlist?list=...`).
   - One-click **Start Stream**, **Stop Stream**, and **Live Status** console.

2. **Dual Streaming Modes**:
   - 💻 **Localhost Mode**: Runs directly on your PC using Python & FFmpeg (Great for testing).
   - ☁️ **Cloud 24/7 Mode (Works when PC is OFF)**: Dispatches the live stream to GitHub Actions cloud runners via GitHub REST API. Broadcasts 24/7 in the cloud without keeping your local computer turned on!

3. **WordPress Web Embed Generator**:
   - Auto-generates custom HTML iframe code to embed on any WordPress page or post.
   - Automatically displays whatever video is broadcasting live on your channel.

---

## 🛠️ How to Test & Run on Localhost

### Step 1: Start the PHP Web Server
Open Command Prompt or Terminal in this folder and run:

**Using XAMPP PHP:**
```cmd
C:\xampp\php\php.exe -S 127.0.0.1:8000
```

**Using System PHP:**
```bash
php -S 127.0.0.1:8000
```

### Step 2: Open in Your Browser
Open your browser and navigate to:
[http://127.0.0.1:8000](http://127.0.0.1:8000)

### Step 3: Configure and Start Streaming
1. Enter your **YouTube Stream Key** (from [YouTube Live Dashboard](https://studio.youtube.com/channel/live/livestreaming)).
2. Paste your **YouTube video URLs** or **Playlist link** into the text box.
3. Click **Save Settings**.
4. Click **Start Live Stream**.

---

## ☁️ Running 24/7 When Your Computer is Powered OFF

To keep the stream broadcasting 24/7 even after you turn off your PC:
1. Push this repository to your GitHub account.
2. Add `YOUTUBE_STREAM_KEY` under your GitHub Repo Settings > **Secrets and variables** > **Actions**.
3. In the PHP Web Dashboard ([http://127.0.0.1:8000](http://127.0.0.1:8000)), choose **☁️ Cloud 24/7 Mode**.
4. Enter your **GitHub Personal Access Token (PAT)** and **Repository Name** (`username/repository`).
5. Click **Start Live Stream**. GitHub Actions will start streaming in the cloud automatically!

---

## 🌐 How to Display on Your WordPress Website

1. On the PHP dashboard, enter your **YouTube Channel ID** (e.g. `UCxxxxxxxx` or `@channel`).
2. Copy the generated HTML snippet:
   ```html
   <iframe width="100%" height="500" src="https://www.youtube.com/embed/live_stream?channel=YOUR_CHANNEL_ID" frameborder="0" allowfullscreen></iframe>
   ```
3. In WordPress Admin:
   - Edit your target Page or Post.
   - Add a **Custom HTML** block.
   - Paste the iframe code and click **Publish / Update**.

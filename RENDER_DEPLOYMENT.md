# Rostrum Production Deployment Guide (Render Free Tier)

This guide provides instructions on how to deploy **Rostrum** on [Render](https://render.com/) using the included `Dockerfile` and `render.yaml` Blueprint.

---

## 🚀 Quick Deployment via Render Blueprint

1. **Push your code to GitHub or GitLab**:
   Ensure all files including `Dockerfile`, `docker/`, `render.yaml`, and `composer.json` are committed and pushed to your git repository.

2. **Create New Blueprint on Render**:
   - Log into [Render Dashboard](https://dashboard.render.com/).
   - Click **New +** -> **Blueprint**.
   - Connect your GitHub / GitLab repository containing Rostrum.
   - Render will detect `render.yaml` and configure the **Web Service** with an attached 1GB Persistent Storage Disk (for SQLite database & generated audio files).

3. **Configure Required Environment Variables**:
   In the Render dashboard under **Environment**:
   - `GEMINI_API_KEY`: Your Google Gemini API Key.
   - `ELEVENLABS_API_KEY`: Your ElevenLabs API Key (optional, fallbacks to Google Translate TTS).
   - `APP_URL`: Set this to your Render service URL (e.g., `https://rostrum.onrender.com`).

4. **Deploy**:
   - Click **Apply**. Render will automatically build the Docker image, run migrations, set up `storage:link`, optimize Laravel caches, and start the app.

---

## 🛠 Features included in this Production Docker Setup

- **Multi-Stage Docker Build**: Node 20 (Vite asset build) + Composer 2 (PHP dependencies) + PHP 8.3 FPM & Nginx (optimized image size).
- **Public Audio Storage Link**: Executes `php artisan storage:link --force` automatically upon container initialization.
- **SQLite with Persistent Storage**: Stores database and generated TTS audio on Render's disk mount (`/var/www/html/storage`).
- **PostgreSQL Support**: Can also switch to Render Free PostgreSQL by setting `DB_CONNECTION=pgsql` and adding `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- **Automatic Caching & Migrations**: Config, route, and view caches are warmed automatically on deployment.

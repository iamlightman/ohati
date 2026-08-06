<?php
// mic_test.php — Standalone Live Web Microphone Diagnostic Tool for Ohati
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microphone & WebRTC Diagnostic Tool — Ohati</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #111827;
            --accent: #E05A47;
            --bg: #F3F4F6;
            --card: #FFFFFF;
            --border: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg); color: #1F2937; padding: 24px 16px; min-height: 100vh; display: flex; justify-content: center; }
        
        .diag-box {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            border: 1px solid var(--border);
            max-width: 580px;
            width: 100%;
            padding: 28px 24px;
        }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-bottom: 4px; }
        .header p { font-size: 0.85rem; color: #6B7280; }

        .test-card {
            background: #F9FAFB;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.88rem;
        }
        .test-title { font-weight: 700; color: var(--primary); }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-error { background: #FEE2E2; color: #991B1B; }
        .badge-warning { background: #FEF3C7; color: #92400E; }

        .alert-iframe {
            background: #EFF6FF;
            border: 1px solid #93C5FD;
            color: #1E40AF;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 14px;
            font-size: 0.83rem;
            line-height: 1.5;
            display: none;
        }

        .btn-run {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
        }
        .btn-run:hover { background: #c94a38; }

        .meter-container {
            margin-top: 20px;
            background: #F3F4F6;
            border-radius: 12px;
            padding: 16px;
            display: none;
            text-align: center;
        }
        .meter-bar-bg {
            height: 16px;
            background: #E5E7EB;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
        }
        .meter-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #10B981, #F59E0B, #EF4444);
            border-radius: 8px;
            transition: width 0.1s ease;
        }

        .log-box {
            background: #1E293B;
            color: #F8FAFC;
            font-family: monospace;
            font-size: 0.78rem;
            padding: 14px;
            border-radius: 12px;
            margin-top: 20px;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <div class="diag-box">
        <div class="header">
            <h1><i class="fa-solid fa-microphone-lines" style="color:var(--accent);"></i> Live Microphone Diagnostics</h1>
            <p>Runs automated WebRTC checks to test microphone permissions & hardware availability</p>
        </div>

        <div id="iframe-warning" class="alert-iframe">
            <strong><i class="fa-solid fa-window-restore"></i> Embed Iframe Frame Detected:</strong><br>
            This page is running inside a frame wrapper. Web browsers automatically disable microphone access inside iframes unless the outer frame specifies <code>allow="microphone"</code>. Please open the link directly in a new browser tab.
        </div>

        <div id="check-protocol" class="test-card">
            <span class="test-title">Secure Connection (HTTPS)</span>
            <span class="badge badge-warning" id="b-protocol">Checking...</span>
        </div>

        <div id="check-api" class="test-card">
            <span class="test-title">MediaDevices API Availability</span>
            <span class="badge badge-warning" id="b-api">Checking...</span>
        </div>

        <div id="check-perm" class="test-card">
            <span class="test-title">Browser Site Permission State</span>
            <span class="badge badge-warning" id="b-perm">Checking...</span>
        </div>

        <div id="check-origin" class="test-card">
            <span class="test-title">Exact Site Origin</span>
            <span style="font-size:0.75rem; font-family:monospace; font-weight:700; color:var(--primary);" id="b-origin">Checking...</span>
        </div>

        <button class="btn-run" onclick="runFullMicrophoneTest()">
            <i class="fa-solid fa-play"></i> Test Microphone Stream Now
        </button>

        <div class="meter-container" id="meter-box">
            <div style="font-weight:700; font-size:0.88rem; color:#10B981;">
                <i class="fa-solid fa-circle-check"></i> Microphone Active & Recording Audio Input!
            </div>
            <div style="font-size:0.78rem; color:#6B7280; margin-top:4px;">Speak into your mic to test audio level:</div>
            <div class="meter-bar-bg">
                <div class="meter-bar-fill" id="meter-fill"></div>
            </div>
        </div>

        <div class="log-box" id="log-console">-- Diagnostic Output Console --\n</div>
    </div>

    <script>
        function log(msg) {
            const consoleEl = document.getElementById('log-console');
            consoleEl.textContent += `[${new Date().toLocaleTimeString()}] ${msg}\n`;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        async function initStaticChecks() {
            log("Running static browser checks...");

            // Check if embedded in iframe
            const isIframe = (window.self !== window.top);
            if (isIframe) {
                document.getElementById('iframe-warning').style.display = 'block';
                log("CRITICAL: Page is embedded in an IFRAME! Browsers block microphone in iframes unless allow='microphone' is set.");
            }

            document.getElementById('b-origin').textContent = location.origin;
            log(`Current Origin: ${location.origin}`);

            // 1. Check Protocol
            const isHttps = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            const bProtocol = document.getElementById('b-protocol');
            if (isHttps) {
                bProtocol.className = 'badge badge-success';
                bProtocol.innerHTML = `<i class="fa-solid fa-lock"></i> Secure (${location.protocol})`;
                log(`Protocol OK: ${location.protocol}`);
            } else {
                bProtocol.className = 'badge badge-error';
                bProtocol.innerHTML = `<i class="fa-solid fa-lock-open"></i> Insecure (${location.protocol})`;
                log(`CRITICAL: Plain HTTP detected (${location.protocol}). Browsers block microphones on HTTP!`);
            }

            // 2. Check MediaDevices API
            const bApi = document.getElementById('b-api');
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                bApi.className = 'badge badge-success';
                bApi.innerHTML = `<i class="fa-solid fa-check"></i> Supported`;
                log("MediaDevices.getUserMedia API is supported.");
            } else {
                bApi.className = 'badge badge-error';
                bApi.innerHTML = `<i class="fa-solid fa-xmark"></i> Unsupported`;
                log("CRITICAL: MediaDevices API is undefined in this browser environment!");
            }

            // 3. Check Permissions API
            const bPerm = document.getElementById('b-perm');
            try {
                if (navigator.permissions && navigator.permissions.query) {
                    const status = await navigator.permissions.query({ name: 'microphone' });
                    updatePermBadge(status.state);
                    status.onchange = () => updatePermBadge(status.state);
                    log(`Permissions API reports status: ${status.state}`);
                } else {
                    bPerm.className = 'badge badge-warning';
                    bPerm.innerHTML = `Unknown (API missing)`;
                    log("Permissions API is not supported on this browser.");
                }
            } catch (e) {
                bPerm.className = 'badge badge-warning';
                bPerm.innerHTML = `Query Restricted`;
                log(`Permissions query notice: ${e.message}`);
            }
        }

        function updatePermBadge(state) {
            const bPerm = document.getElementById('b-perm');
            if (state === 'granted') {
                bPerm.className = 'badge badge-success';
                bPerm.innerHTML = `<i class="fa-solid fa-check"></i> Granted (Allowed)`;
            } else if (state === 'denied') {
                bPerm.className = 'badge badge-error';
                bPerm.innerHTML = `<i class="fa-solid fa-ban"></i> Denied (Blocked)`;
            } else {
                bPerm.className = 'badge badge-warning';
                bPerm.innerHTML = `<i class="fa-solid fa-clock"></i> Prompt Required`;
            }
        }

        let audioContext = null;
        let meterStream = null;

        async function runFullMicrophoneTest() {
            log("------------------------------------------");
            log("Requesting navigator.mediaDevices.getUserMedia({ audio: true })...");

            const meterBox = document.getElementById('meter-box');
            const meterFill = document.getElementById('meter-fill');
            meterBox.style.display = 'none';

            if (meterStream) {
                meterStream.getTracks().forEach(t => t.stop());
                meterStream = null;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                
                meterStream = stream;
                const audioTracks = stream.getAudioTracks();
                log(`SUCCESS! Stream acquired. Audio Tracks: ${audioTracks.length}`);
                audioTracks.forEach((t, i) => {
                    log(`  Track [${i+1}]: ${t.label} (Enabled: ${t.enabled}, ReadyState: ${t.readyState}, Muted: ${t.muted})`);
                });

                // Display Visual Audio Meter
                meterBox.style.display = 'block';
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                source.connect(analyser);

                const dataArray = new Uint8Array(analyser.frequencyBinCount);
                function updateMeter() {
                    if (!meterStream) return;
                    analyser.getByteFrequencyData(dataArray);
                    let sum = 0;
                    for (let i = 0; i < dataArray.length; i++) {
                        sum += dataArray[i];
                    }
                    let average = sum / dataArray.length;
                    let percentage = Math.min(100, Math.round((average / 128) * 100));
                    meterFill.style.width = percentage + '%';
                    requestAnimationFrame(updateMeter);
                }
                updateMeter();

            } catch (err) {
                log(`ERROR CAUGHT:`);
                log(`  Name: ${err.name}`);
                log(`  Message: ${err.message}`);
                alert("Microphone Test Result: " + err.name + "\nMessage: " + err.message);
            }
        }

        window.addEventListener('DOMContentLoaded', initStaticChecks);
    </script>
</body>
</html>

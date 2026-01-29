<?php
/**
 * player.php - Antarmuka JW Player
 * Terintegrasi dengan Cloudflare Worker untuk streaming super cepat.
 */

error_reporting(0);

// ===================================
// ====== KONFIGURASI WORKER     ======
// ===================================
// Ganti dengan URL Worker Anda jika berubah
$workerProxyUrl = "https://drive.andrias.workers.dev/"; 
$apiBaseUrl = "https://cloud.hownetwork.xyz/api.php";

// ===================================
// ====== AMBIL DATA DARI API  ======
// ===================================
$id = $_GET['id'] ?? '';
$src = $_GET['src'] ?? $_GET['u'] ?? '';
$poster = '';
$title = 'Video Player';

if ($id !== '') {
    $api_url = $apiBaseUrl . "?id=" . rawurlencode($id);
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0...',
        CURLOPT_REFERER        => 'https://cloud.hownetwork.xyz/',
        CURLOPT_TIMEOUT        => 5
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    if ($res) {
        $json = json_decode($res, true);
        $src = $json['file'] ?? $src;
        $poster = $json['poster'] ?? '';
        $title = $json['title'] ?? $title;
    }
}

if ($src === '') die("Video tidak ditemukan. Gunakan parameter ?id= atau ?u=");

// BUNGKUS URL VIDEO DENGAN CLOUDFLARE WORKER
$finalVideoUrl = $workerProxyUrl . "?u=" . rawurlencode($src);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://content.jwplatform.com/libraries/KB5zFt7A.js"></script>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; width: 100%; background: #000; overflow: hidden; font-family: sans-serif; }
        #player { width: 100% !important; height: 100% !important; }
        
        /* Watermark Putih Transparan */
        .watermark {
            position: absolute;
            bottom: 60px;
            left: 20px;
            color: rgba(255, 255, 255, 0.4);
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
            pointer-events: none;
            z-index: 10;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
            text-transform: uppercase;
        }

        /* Modal Resume Modern */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; align-items:center; justify-content:center; backdrop-filter:blur(5px); }
        .m-box { background:#1a1a1a; padding:30px; border-radius:15px; text-align:center; color:#fff; width:300px; border:1px solid #333; }
        .btns { display:flex; gap:10px; margin-top:20px; }
        .btn { flex:1; padding:12px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; }
        .btn-p { background:#e50914; color:#fff; }
        .btn-s { background:#333; color:#fff; }
    </style>
</head>
<body>
    <div id="player"></div>
    <div class="watermark">ANDRIAS PLAYER</div>

    <div id="m" class="modal">
        <div class="m-box">
            <h3 style="margin-top:0">Lanjutkan Nonton?</h3>
            <p>Terakhir menonton sampai menit <b id="mt">00:00</b></p>
            <div class="btns">
                <button id="br" class="btn btn-s">Dari Awal</button>
                <button id="bl" class="btn btn-p">Lanjutkan</button>
            </div>
            <div style="font-size:9px; margin-top:15px; opacity:0.5; letter-spacing:1px">ANDRIAS PLAYER</div>
        </div>
    </div>

    <script>
        const videoId = <?= json_encode($id ?: md5($src)) ?>;
        const progressData = JSON.parse(localStorage.getItem('mt_progress') || '{}');
        const saved = progressData[videoId];
        const hasProgress = saved && saved.seconds > 10 && saved.percent < 95;

        const player = jwplayer("player").setup({
            file: "<?= $finalVideoUrl ?>",
            image: "<?= htmlspecialchars($poster) ?>",
            width: "100%",
            height: "100%",
            autostart: !hasProgress,
            playbackRateControls: true,
            cast: {}
        });

        player.on('ready', () => {
            // Sembunyikan tombol fullscreen bawaan jika Anda ingin pakai kustom
            // document.querySelector('.jw-icon-fullscreen').style.display = 'none';
            
            if (hasProgress) {
                const modal = document.getElementById('m');
                const m = Math.floor(saved.seconds / 60);
                const s = Math.floor(saved.seconds % 60).toString().padStart(2, '0');
                document.getElementById('mt').textContent = m + ":" + s;
                modal.style.display = 'flex';
                
                document.getElementById('bl').onclick = () => { 
                    modal.style.display = 'none'; 
                    player.play(); 
                    player.seek(saved.seconds); 
                };
                document.getElementById('br').onclick = () => { 
                    modal.style.display = 'none'; 
                    player.play(); 
                };
            }
        });

        player.on('time', (e) => {
            if (videoId) {
                const prog = JSON.parse(localStorage.getItem('mt_progress') || '{}');
                prog[videoId] = { seconds: e.position, percent: (e.position/e.duration)*100 };
                localStorage.setItem('mt_progress', JSON.stringify(prog));
            }
        });
    </script>
</body>
</html>
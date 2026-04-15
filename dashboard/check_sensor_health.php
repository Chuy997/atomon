<?php
declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');
$servername = "localhost";
$dbuser     = "jmuro";
$dbpassword = "Monday.03";
$dbname     = "atomon";

$conn = new mysqli($servername, $dbuser, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// --- Handle AJAX toggle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['sensor_id'], $input['alerts_muted'])) {
        $sid  = (int)$input['sensor_id'];
        $mute = (int)$input['alerts_muted'];
        $stmt = $conn->prepare("UPDATE sensor_config SET alerts_muted = ? WHERE sensor_id = ?");
        $stmt->bind_param("ii", $mute, $sid);
        echo json_encode(['success' => $stmt->execute()]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    }
    $conn->close();
    exit;
}

// --- Fetch sensor data for HTML view ---
$sensors = [];
$result = $conn->query("
    SELECT c.sensor_id, c.alerts_muted,
           (SELECT MAX(s.timestamp) FROM sensores s WHERE s.sensor_id = c.sensor_id) AS last_seen
    FROM sensor_config c
    ORDER BY c.sensor_id ASC
");
while ($row = $result->fetch_assoc()) {
    $lastSeen = $row['last_seen'];
    $diffMin  = null;
    $isDown   = false;
    if ($lastSeen === null) {
        $isDown = true;
    } else {
        $diffSec = time() - strtotime($lastSeen);
        $diffMin = (int)floor($diffSec / 60);
        $isDown  = $diffSec > (20 * 60);
    }
    $row['is_down']   = $isDown;
    $row['diff_min']  = $diffMin;
    $sensors[] = $row;
}
$conn->close();

$totalDown   = count(array_filter($sensors, fn($s) => $s['is_down']));
$totalOnline = count($sensors) - $totalDown;
$totalMuted  = count(array_filter($sensors, fn($s) => $s['alerts_muted']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>ATOMON · Monitor de Sensores</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:          #0f1117;
      --surface:     #161b27;
      --surface-2:   #1e2535;
      --border:      rgba(255,255,255,0.07);
      --text:        #e2e8f0;
      --muted:       #64748b;
      --brand:       #6366f1;
      --brand-light: rgba(99,102,241,0.15);
      --ok:          #22c55e;
      --ok-light:    rgba(34,197,94,0.12);
      --warn:        #f59e0b;
      --warn-light:  rgba(245,158,11,0.12);
      --bad:         #ef4444;
      --bad-light:   rgba(239,68,68,0.12);
      --radius:      12px;
      --radius-sm:   8px;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      padding: 0 0 60px;
    }

    /* ── Topbar ── */
    .topbar {
      background: rgba(22, 27, 39, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      padding: 0 32px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .topbar-brand .logo-dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: var(--brand);
      box-shadow: 0 0 10px var(--brand);
    }
    .topbar-brand h1 {
      font-size: 16px;
      font-weight: 600;
      letter-spacing: 0.3px;
      color: #f1f5f9;
    }
    .topbar-brand span {
      font-size: 13px;
      color: var(--muted);
      font-weight: 400;
    }
    .topbar-meta {
      font-size: 13px;
      color: var(--muted);
    }

    /* ── Page wrapper ── */
    .page {
      max-width: 1100px;
      margin: 40px auto;
      padding: 0 24px;
    }

    /* ── Summary strip ── */
    .summary-strip {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }
    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .stat-card .label {
      font-size: 12px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--muted);
    }
    .stat-card .value {
      font-size: 36px;
      font-weight: 700;
      line-height: 1;
    }
    .stat-card.ok  .value { color: var(--ok); }
    .stat-card.bad .value { color: var(--bad); }
    .stat-card.muted-card .value { color: var(--warn); }

    /* ── Section header ── */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
    }
    .section-header h2 {
      font-size: 15px;
      font-weight: 600;
      color: #f1f5f9;
    }
    .section-header .hint {
      font-size: 13px;
      color: var(--muted);
    }

    /* ── Sensor Grid ── */
    .sensor-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 16px;
    }

    /* ── Sensor Card ── */
    .sensor-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .sensor-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    }
    .sensor-card.card-ok     { border-color: rgba(34,197,94,0.3); }
    .sensor-card.card-down   { border-color: rgba(239,68,68,0.45); }
    .sensor-card.card-muted  { border-color: rgba(245,158,11,0.35); }

    .card-top {
      padding: 16px 20px 14px;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
    }
    .card-left { display: flex; flex-direction: column; gap: 4px; }
    .sensor-id {
      font-size: 17px;
      font-weight: 700;
      color: #f1f5f9;
    }
    .sensor-last-seen {
      font-size: 12px;
      color: var(--muted);
    }

    /* Status badge */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 99px;
    }
    .badge-dot { width: 7px; height: 7px; border-radius: 50%; }
    .badge.ok  { background: var(--ok-light); color: var(--ok); border: 1px solid rgba(34,197,94,0.3); }
    .badge.ok  .badge-dot { background: var(--ok); box-shadow: 0 0 5px var(--ok); animation: pulse-ok 2s infinite; }
    .badge.bad { background: var(--bad-light); color: var(--bad); border: 1px solid rgba(239,68,68,0.3); }
    .badge.bad .badge-dot { background: var(--bad); box-shadow: 0 0 5px var(--bad); animation: pulse-bad 1.2s infinite; }
    .badge.no-mail { background: var(--brand-light); color: #818cf8; border: 1px solid rgba(99,102,241,0.25); font-size: 11px; }

    @keyframes pulse-ok {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.45; }
    }
    @keyframes pulse-bad {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.3; }
    }

    /* Downtime bar */
    .downtime-bar {
      background: var(--bad-light);
      border-left: 3px solid var(--bad);
      padding: 8px 20px;
      font-size: 12px;
      color: #fca5a5;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .downtime-bar.hidden { display: none; }

    /* Card bottom — toggle area */
    .card-bottom {
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .toggle-label {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .toggle-label strong {
      font-size: 13px;
      font-weight: 600;
      color: #f1f5f9;
    }
    .toggle-label small {
      font-size: 11px;
      color: var(--muted);
    }

    /* iOS-style toggle switch */
    .toggle-wrap { display: flex; align-items: center; gap: 10px; }
    .toggle-text {
      font-size: 12px;
      font-weight: 500;
      min-width: 70px;
      text-align: right;
    }
    /* Switch encendido = verde = alertas ACTIVAS; apagado = gris = silenciado */
    .toggle-text.active  { color: var(--ok); }
    .toggle-text.muted   { color: var(--muted); }

    .switch { position: relative; display: inline-block; width: 50px; height: 27px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
      position: absolute; inset: 0;
      background: #1e293b;
      border: 1px solid rgba(255,255,255,0.1);
      cursor: pointer;
      border-radius: 27px;
      transition: background 0.3s, border-color 0.3s;
    }
    .slider::before {
      content: '';
      position: absolute;
      height: 19px; width: 19px;
      left: 3px; bottom: 3px;
      background: #4b5563;
      border-radius: 50%;
      transition: transform 0.3s, background 0.3s;
    }
    /* checked = Alertas ACTIVAS → verde */
    input:checked + .slider {
      background: rgba(34,197,94,0.2);
      border-color: rgba(34,197,94,0.5);
    }
    input:checked + .slider::before {
      transform: translateX(23px);
      background: var(--ok);
    }

    /* Toast */
    #toast {
      position: fixed;
      bottom: 24px; right: 24px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px 18px;
      font-size: 13px;
      color: var(--text);
      opacity: 0;
      transform: translateY(12px);
      transition: all 0.25s ease;
      z-index: 999;
      pointer-events: none;
      max-width: 280px;
    }
    #toast.show { opacity: 1; transform: translateY(0); }
    #toast.success { border-left: 3px solid var(--ok); }
    #toast.error   { border-left: 3px solid var(--bad); }

    /* Refresh link */
    .refresh-btn {
      background: var(--surface-2);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: var(--radius-sm);
      padding: 7px 14px;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      transition: border-color 0.2s;
    }
    .refresh-btn:hover { border-color: rgba(99,102,241,0.5); }

    /* Switch deshabilitado para sensor sin correo */
    .switch.disabled { opacity: 0.35; pointer-events: none; cursor: not-allowed; }
    .badge-visual {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 99px;
      background: rgba(99,102,241,0.1); color: #818cf8;
      border: 1px solid rgba(99,102,241,0.25);
    }

    /* Divider */
    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 32px 0;
    }

    /* Responsive */
    @media (max-width: 600px) {
      .topbar { padding: 0 16px; }
      .page   { padding: 0 16px; margin-top: 24px; }
    }
  </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="logo-dot"></div>
    <h1>ATOMON &nbsp;<span>/ Monitor de Sensores</span></h1>
  </div>
  <span class="topbar-meta"><?= date('d M Y, H:i') ?> · Hora MX</span>
</header>

<!-- Page -->
<main class="page">

  <!-- Summary strip -->
  <div class="summary-strip">
    <div class="stat-card ok">
      <span class="label">En línea</span>
      <span class="value"><?= $totalOnline ?></span>
    </div>
    <div class="stat-card bad">
      <span class="label">Desconectados</span>
      <span class="value"><?= $totalDown ?></span>
    </div>
    <div class="stat-card muted-card">
      <span class="label">Silenciados</span>
      <span class="value"><?= $totalMuted ?></span>
    </div>
    <div class="stat-card">
      <span class="label">Total sensores</span>
      <span class="value" style="color:var(--brand)"><?= count($sensors) ?></span>
    </div>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <h2>Estado individual por sensor</h2>
    <div style="display:flex;gap:10px;align-items:center">
      <span class="hint">Auto-refresco cada 60 s</span>
      <a href="check_sensor_health.php" class="refresh-btn">↻ Actualizar</a>
    </div>
  </div>

  <!-- Sensor cards -->
  <div class="sensor-grid">
  <?php foreach ($sensors as $s):
    $sid    = (int)$s['sensor_id'];
    $isDown = $s['is_down'];
    $muted  = (int)$s['alerts_muted'];
    $min    = $s['diff_min'];
    $noMail = $sid === 6;

    $cardClass  = $isDown ? 'card-down' : ($muted ? 'card-muted' : 'card-ok');
    $badgeClass = $isDown ? 'bad' : 'ok';
    $badgeText  = $isDown ? 'Desconectado' : 'En línea';

    if ($s['last_seen'] === null) {
      $lastSeenText = 'Sin registros aún';
    } else {
      $ts = new DateTime($s['last_seen']);
      $lastSeenText = 'Último dato: ' . $ts->format('d/m/Y H:i:s');
    }

    $downText = '';
    if ($isDown && $min !== null) {
      $downText = "Sin datos hace {$min} min (umbral: 20 min)";
    } elseif ($isDown) {
      $downText = "Nunca ha reportado datos";
    }
  ?>
    <div class="sensor-card <?= $cardClass ?>" id="card-sensor-<?= $sid ?>">

      <!-- Card top -->
      <div class="card-top">
        <div class="card-left">
          <span class="sensor-id">Sensor <?= $sid ?></span>
          <span class="sensor-last-seen"><?= htmlspecialchars($lastSeenText) ?></span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
          <span class="badge <?= $badgeClass ?>">
            <span class="badge-dot"></span>
            <?= $badgeText ?>
          </span>
          <?php if ($noMail): ?>
          <span class="badge no-mail">Sin correo</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Downtime bar -->
      <div class="downtime-bar <?= !$isDown ? 'hidden' : '' ?>" id="bar-<?= $sid ?>">
        ⚠ <?= htmlspecialchars($downText) ?>
      </div>

      <!-- Card bottom — toggle -->
      <!-- Switch ON (checked) = alerts_muted=0 = Alertas ACTIVAS -->
      <!-- Switch OFF (unchecked) = alerts_muted=1 = Silenciado -->
      <div class="card-bottom">
        <div class="toggle-label">
          <strong><?= $noMail ? 'Monitoreo visual' : 'Alertas activas' ?></strong>
          <?php if ($noMail): ?>
            <small>No envía correos &mdash; solo visible en el panel</small>
          <?php else: ?>
            <small><?= $muted ? 'Silenciado &mdash; sin envío de correos' : 'Monitoreando &mdash; enviará correo si falla' ?></small>
          <?php endif; ?>
        </div>
        <div class="toggle-wrap">
          <?php if ($noMail): ?>
            <span class="badge-visual">Solo visual</span>
          <?php else: ?>
            <span class="toggle-text <?= !$muted ? 'active' : 'muted' ?>" id="lbl-<?= $sid ?>">
              <?= !$muted ? 'Activo' : 'Silenciado' ?>
            </span>
            <label class="switch" title="<?= !$muted ? 'Clic para silenciar alertas' : 'Clic para reactivar alertas' ?>">
              <input type="checkbox"
                     id="toggle-<?= $sid ?>"
                     <?= !$muted ? 'checked' : '' ?>
                     onchange="handleToggle(<?= $sid ?>, this.checked)">
              <span class="slider"></span>
            </label>
          <?php endif; ?>
        </div>
      </div>

    </div>
  <?php endforeach; ?>
  </div>

  <hr class="divider">
  
</main>

<!-- Toast -->
<div id="toast"></div>

<script>
// isActive = true  → el switch está encendido (checked) → alerts_muted = 0 → alertas ACTIVAS
// isActive = false → el switch está apagado             → alerts_muted = 1 → SILENCIADO
async function handleToggle(sensorId, isActive) {
  const lbl    = document.getElementById(`lbl-${sensorId}`);
  const card   = document.getElementById(`card-sensor-${sensorId}`);
  const bar    = document.getElementById(`bar-${sensorId}`);
  const chk    = document.getElementById(`toggle-${sensorId}`);
  const toggle = card.querySelector('.switch');

  // alerts_muted es la INVERSA del switch: ON=activo=0, OFF=silenciado=1
  const mutedValue = isActive ? 0 : 1;

  try {
    const res = await fetch('check_sensor_health.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ sensor_id: sensorId, alerts_muted: mutedValue })
    });
    const json = await res.json();

    if (json.success) {
      // Actualizar el texto y color del label
      lbl.textContent = isActive ? 'Activo' : 'Silenciado';
      lbl.className   = isActive ? 'toggle-text active' : 'toggle-text muted';

      // Actualizar subtítulo de la tarjeta
      const small = card.querySelector('.toggle-label small');
      if (small && !small.textContent.includes('Sensor 6')) {
        small.textContent = isActive
          ? 'Monitoreando — enviará correo si falla'
          : 'Silenciado — sin envío de correos';
      }
      toggle.title = isActive ? 'Clic para silenciar alertas' : 'Clic para reactivar alertas';

      // Actualizar clase del borde de la tarjeta
      const isDown = bar && !bar.classList.contains('hidden');
      card.classList.remove('card-ok','card-down','card-muted');
      if (isDown) {
        card.classList.add('card-down');
      } else {
        card.classList.add(isActive ? 'card-ok' : 'card-muted');
      }

      showToast(
        isActive
          ? `🔔 Sensor ${sensorId}: alertas reactivadas.`
          : `🔕 Sensor ${sensorId}: alertas silenciadas.`,
        'success'
      );
    } else {
      showToast(`Error al guardar: ${json.message || ''}`, 'error');
      // Revertir el switch al estado anterior
      chk.checked = !isActive;
    }
  } catch(e) {
    showToast('Error de red. Intenta de nuevo.', 'error');
    chk.checked = !isActive;
  }
}

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `show ${type}`;
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.className = '', 3500);
}

// Auto-refresh every 60 s
setTimeout(() => location.reload(), 60_000);
</script>

</body>
</html>

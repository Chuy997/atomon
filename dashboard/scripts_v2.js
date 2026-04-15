// ===== Estado global =====
let temperatureChart = null;
let humidityChart = null;
let currentRangeMinutes = 180; // default 3h (coincide con tu backend)

// ===== Init =====
document.addEventListener('DOMContentLoaded', () => {
  wireUI();
  updateClock();
  setInterval(updateClock, 1000);
  loadAndRender();

  // refresco automático cada 2 min (igual que tu versión)
  setInterval(loadAndRender, 2 * 60 * 1000);
});

function wireUI(){
  // Rango de tiempo (segmentado)
  document.querySelectorAll('.segmented-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.segmented-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      currentRangeMinutes = parseInt(btn.dataset.range,10);
      loadAndRender();
    });
  });

  // Botón refrescar
  document.getElementById('btnRefresh').addEventListener('click', loadAndRender);
}

function updateClock(){
  const el = document.getElementById('clock');
  const now = new Date();
  const dd = now.toLocaleDateString('es-MX');
  const hh = now.toLocaleTimeString('es-MX', {hour12:false});
  el.textContent = `${dd}  ${hh}`;
}

// ===== Data fetch + render =====
async function loadAndRender(){
  try{
    loadSensorConfig(); // Refresh sensor states and muted buttons
    const res = await fetch('../get_data.php');
    const json = await res.json();
    if(json.status !== 'success'){
      console.error('API error:', json);
      return;
    }
    render(json.data);
  }catch(err){
    console.error('Error fetching data:', err);
  }
}

function render(rows){
  if(!Array.isArray(rows)) rows = [];

  // Filtrado por sensores 1..5 (igual que el original)
  const filtered = rows.filter(r => {
    const id = parseInt(r.sensor_id,10);
    return id >= 1 && id <= 5;
  });

  // Ordenar por tiempo ascendente
  filtered.sort((a,b)=> new Date(a.timestamp) - new Date(b.timestamp));

  // Filtrar por rango actual (minutos atrás)
  const now = new Date();
  const since = new Date(now.getTime() - currentRangeMinutes * 60 * 1000);
  const inRange = filtered.filter(r => new Date(r.timestamp) >= since);

  // KPI
  const temps = inRange.filter(r => r.temperatura !== null).map(r => parseFloat(r.temperatura));
  const hums  = inRange.filter(r => r.humedad !== null).map(r => parseFloat(r.humedad));
  const avgT = temps.length ? round2(temps.reduce((a,b)=>a+b,0) / temps.length) : null;
  const avgH = hums.length ? round2(hums.reduce((a,b)=>a+b,0) / hums.length) : null;

  setKPI(avgT, avgH);

  // Labels únicos
  const uniqueTs = [...new Set(inRange.map(r=>r.timestamp))].sort((a,b)=> new Date(a)-new Date(b));
  const labels = uniqueTs.map(ts => fmtTime(ts));

  // Agrupar por sensor
  const bySensor = groupBySensor(inRange, uniqueTs);

  // Datasets por sensor
  const palette = [
    'rgba(122,162,255,0.9)',
    'rgba(144,238,144,0.9)',
    'rgba(255,189,89,0.9)',
    'rgba(255,126,171,0.9)',
    'rgba(146,230,255,0.9)'
  ];

  let colorIdx = 0;
  const tempDatasets = [];
  const humDatasets = [];

  Object.keys(bySensor).forEach(sensorId=>{
    const s = bySensor[sensorId];
    const col = palette[colorIdx++ % palette.length];

    tempDatasets.push({
      label: `Línea ${sensorId}`,
      data: uniqueTs.map(ts => s.temperature.get(ts) ?? null),
      borderColor: col, backgroundColor: col, fill: false, tension: .25, pointRadius: 2
    });

    humDatasets.push({
      label: `Línea ${sensorId}`,
      data: uniqueTs.map(ts => s.humidity.get(ts) ?? null),
      borderColor: col, backgroundColor: col, fill: false, tension: .25, pointRadius: 2
    });
  });

  // Promedios por timestamp
  const avgTempPerTs = uniqueTs.map(ts => {
    const arr = inRange.filter(r=>r.timestamp===ts && r.temperatura!=null).map(r=>parseFloat(r.temperatura));
    return arr.length ? round2(arr.reduce((a,b)=>a+b,0)/arr.length) : null;
  });
  const avgHumPerTs = uniqueTs.map(ts => {
    const arr = inRange.filter(r=>r.timestamp===ts && r.humedad!=null).map(r=>parseFloat(r.humedad));
    return arr.length ? round2(arr.reduce((a,b)=>a+b,0)/arr.length) : null;
  });

  tempDatasets.push({
    label:'Promedio', data: avgTempPerTs, borderColor:'rgba(37,179,106,1)', backgroundColor:'rgba(37,179,106,.3)',
    tension:.25, borderWidth:2, pointRadius:0
  });
  humDatasets.push({
    label:'Promedio', data: avgHumPerTs, borderColor:'rgba(37,179,106,1)', backgroundColor:'rgba(37,179,106,.3)',
    tension:.25, borderWidth:2, pointRadius:0
  });

  // Límites
  tempDatasets.push(lineDataset('Mín (10°C)', 10));
  tempDatasets.push(lineDataset('Máx (30°C)', 30));
  humDatasets.push(lineDataset('Mín (30%)', 30));
  humDatasets.push(lineDataset('Máx (75%)', 75));

  // Render charts
  renderLineChart('temperatureChart', labels, tempDatasets, 5, 40, (c)=> temperatureChart = c, temperatureChart);
  renderLineChart('humidityChart', labels, humDatasets, 15, 85, (c)=> humidityChart = c, humidityChart);

  // Última actualización
  const lastTs = uniqueTs.length ? uniqueTs[uniqueTs.length-1] : null;
  document.getElementById('last-update').textContent = lastTs ? `Actualizado: ${fmtDateTime(lastTs)}` : 'Sin datos';
}

function groupBySensor(rows, uniqueTs){
  const out = {};
  rows.forEach(r=>{
    const id = r.sensor_id;
    if(!out[id]) out[id] = {temperature:new Map(), humidity:new Map()};
    out[id].temperature.set(r.timestamp, r.temperatura!=null ? parseFloat(r.temperatura) : null);
    out[id].humidity.set(r.timestamp, r.humedad!=null ? parseFloat(r.humedad) : null);
  });
  return out;
}

function lineDataset(label, value){
  return {
    label, data: new Proxy([], {get:(_,prop)=> (prop==='length'?0:undefined)}), // placeholder; se fija en y.ticks.suggestedMin/Max
    // truco: usamos scriptable para pintar línea horizontal
    borderDash: [6,6], borderColor:'rgba(154,161,175,.9)', pointRadius:0, fill:false,
    // plugin para dibujar la línea
    customValue: value
  };
}

function renderLineChart(canvasId, labels, datasets, minY, maxY, setRef, existing){
  // Ajuste: convertimos datasets de línea horizontal a plugin draw
  const hLines = datasets.filter(d => d.customValue !== undefined);
  const realDatasets = datasets.filter(d => d.customValue === undefined);

  const options = {
    responsive:true,
    interaction:{mode:'index', intersect:false},
    plugins:{
      legend:{labels:{color:'#d7d9df'}},
      tooltip:{backgroundColor:'#1a1f2b', titleColor:'#d7d9df', bodyColor:'#d7d9df', borderColor:'rgba(255,255,255,.08)', borderWidth:1}
    },
    scales:{
      x:{ticks:{color:'#9aa1af'}, grid:{color:'rgba(255,255,255,.06)'}},
      y:{
        min:minY, max:maxY,
        ticks:{color:'#9aa1af', stepSize:5},
        grid:{color:'rgba(255,255,255,.06)'}
      }
    }
  };

  const hLinesPlugin = {
    id:'hLines',
    afterDatasetsDraw(chart){
      const {ctx, chartArea:{left,right}, scales:{y}} = chart;
      hLines.forEach(d=>{
        const yPx = y.getPixelForValue(d.customValue);
        ctx.save();
        ctx.strokeStyle = d.borderColor;
        ctx.setLineDash(d.borderDash);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(left, yPx);
        ctx.lineTo(right, yPx);
        ctx.stroke();
        ctx.restore();
      });
    }
  };

  if(existing){
    existing.data.labels = labels;
    existing.data.datasets = realDatasets;
    existing.options.scales.y.min = minY;
    existing.options.scales.y.max = maxY;
    existing.update();
  }else{
    const ctx = document.getElementById(canvasId).getContext('2d');
    const chart = new Chart(ctx, {type:'line', data:{labels, datasets: realDatasets}, options, plugins:[hLinesPlugin]});
    setRef(chart);
  }
}

// ===== KPI y estados =====
function setKPI(avgT, avgH){
  const tEl = document.getElementById('avg-temperature');
  const hEl = document.getElementById('avg-humidity');
  tEl.textContent = (avgT!=null ? avgT : '--');
  hEl.textContent = (avgH!=null ? avgH : '--');

  // Estados individuales
  const tempStatus = statusFromRange(avgT, 10, 30);
  const humStatus  = statusFromRange(avgH, 30, 75);

  setChip('temp-status', tempStatus);
  setChip('hum-status', humStatus);

  setCardState('card-temp', tempStatus);
  setCardState('card-hum', humStatus);

  // Estado global (si alguno rojo → rojo; si no, si alguno amarillo → amarillo; si todos ok → verde)
  let globalStatus = 'ok';
  if (tempStatus === 'bad' || humStatus === 'bad') {
    globalStatus = 'bad';
  } else if (tempStatus === 'warn' || humStatus === 'warn') {
    globalStatus = 'warn';
  }

  // Cambiar clase del body
  document.body.classList.remove('green','yellow','red');
  switch(globalStatus){
    case 'ok':
      document.body.classList.add('green');
      break;
    case 'warn':
      document.body.classList.add('yellow');
      break;
    case 'bad':
      document.body.classList.add('red');
      break;
  }
}


function statusFromRange(val, min, max){
  if(val==null) return 'warn';
  if(val < min || val > max) return 'bad';
  const warnBand = (max-min) * 0.1; // banda ±10%
  if(val < (min+warnBand) || val > (max-warnBand)) return 'warn';
  return 'ok';
}

function setChip(id, state){
  const el = document.getElementById(id);
  el.classList.remove('ok','warn','bad');
  let text='--';
  if(state==='ok'){ text='En rango'; el.classList.add('ok'); }
  if(state==='warn'){ text='Precaución'; el.classList.add('warn'); }
  if(state==='bad'){ text='Alerta'; el.classList.add('bad'); }
  el.textContent = text;
}

function setCardState(id, state){
  const el = document.getElementById(id);
  el.style.boxShadow = ({
    ok:   '0 0 0 1px rgba(37,179,106,.35), 0 10px 30px rgba(0,0,0,.35)',
    warn: '0 0 0 1px rgba(240,180,41,.35), 0 10px 30px rgba(0,0,0,.35)',
    bad:  '0 0 0 1px rgba(224,86,91,.35), 0 10px 30px rgba(0,0,0,.35)',
  })[state] || 'var(--shadow)';
}

// ===== Utils =====
function fmtTime(ts){
  const d = new Date(ts);
  const hh = String(d.getHours()).padStart(2,'0');
  const mm = String(d.getMinutes()).padStart(2,'0');
  return `${hh}:${mm}`;
}
function fmtDateTime(ts){
  const d = new Date(ts);
  const date = d.toLocaleDateString('es-MX');
  const time = d.toLocaleTimeString('es-MX', {hour12:false});
  return `${date} ${time}`;
}
const round2 = n => Math.round(n * 100) / 100;

// ===== Interfaz de Mute de Sensores y Desconexión =====
async function loadSensorConfig() {
  try {
    const res = await fetch('get_sensor_config.php');
    if(!res.ok) return;
    const data = await res.json();
    renderSensorStatus(data);
  } catch(e) { console.error('Error fetching sensor config', e); }
}

function renderSensorStatus(data) {
  const grid = document.getElementById('sensor-status-grid');
  if(!grid) return;
  grid.innerHTML = '';
  data.forEach(s => {
    const tglId = `mute-s${s.sensor_id}`;
    const div = document.createElement('div');
    div.className = `sensor-pill ${s.is_down ? 'offline' : ''}`;
    div.innerHTML = `
      <div class="sensor-pill-header">
        <span class="sensor-name">Sensor ${s.sensor_id} ${s.sensor_id == 6 ? '<span style="font-size:11px;font-weight:normal;opacity:0.7">(Sin correo)</span>' : ''}</span>
        <div class="sensor-indicator ${s.is_down ? 'offline' : ''}" title="${s.is_down ? 'Desconectado (>20m)' : 'En línea'}"></div>
      </div>
      <div class="sensor-mute-row">
        <span>Silenciar Alerta</span>
        <label class="switch">
          <input type="checkbox" id="${tglId}" ${s.alerts_muted == 1 ? 'checked' : ''} onchange="toggleMute(${s.sensor_id}, this.checked)">
          <span class="slider"></span>
        </label>
      </div>
    `;
    grid.appendChild(div);
  });
}

window.toggleMute = async function(sensorId, isMuted) {
  try {
    const res = await fetch('toggle_sensor_mute.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({sensor_id: sensorId, alerts_muted: isMuted ? 1 : 0})
    });
    const parsed = await res.json();
    if(parsed.success) {
      console.log('Mute config up to date');
    } else {
      alert("Error guardando config: " + (parsed.message || ""));
    }
  } catch(e) {
    console.error('Error toggling mute', e);
  }
}

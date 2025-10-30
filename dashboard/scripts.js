let temperatureChartInstance = null;
let humidityChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
  fetchData();
  setInterval(fetchData, 2 * 60 * 1000);
});

async function fetchData() {
  try {
    const response = await fetch('../get_data.php');
    const result = await response.json();

    if (result.status !== 'success') {
      alert(`Error: ${result.message}`);
      console.error('Respuesta del servidor:', result);
      return;
    }

    const data = result.data;

    // Filtrar datos: solo sensores 1 a 5
    const filteredData = data.filter(entry => {
      const id = parseInt(entry.sensor_id, 10);
      return id >= 1 && id <= 5;
    });

    // Calcular promedios de los últimos 15 minutos
    const now = new Date();
    const fifteenMinutesAgo = new Date(now.getTime() - 15 * 60 * 1000);
    const recentData = filteredData.filter(entry => new Date(entry.timestamp) >= fifteenMinutesAgo);

    const tempValues = recentData
      .filter(entry => entry.temperatura !== null)
      .map(entry => parseFloat(entry.temperatura));
    const overallTemp = tempValues.length
      ? parseFloat((tempValues.reduce((a, b) => a + b, 0) / tempValues.length).toFixed(2))
      : '--';

    const humidityValues = recentData
      .filter(entry => entry.humedad !== null)
      .map(entry => parseFloat(entry.humedad));
    const overallHumidity = humidityValues.length
      ? parseFloat((humidityValues.reduce((a, b) => a + b, 0) / humidityValues.length).toFixed(2))
      : '--';

    document.getElementById('avg-temperature').innerText = `${overallTemp} °C`;
    document.getElementById('avg-humidity').innerText = `${overallHumidity} %`;

    // Ordenar los datos filtrados por timestamp
    filteredData.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
    const uniqueTimestamps = [...new Set(filteredData.map(entry => entry.timestamp))];
    uniqueTimestamps.sort((a, b) => new Date(a) - new Date(b));
    const labels = uniqueTimestamps.map(entry => formatTimestamp(entry));

    // Agrupar datos por sensor
    const sensors = {};
    filteredData.forEach(entry => {
      const sensorId = entry.sensor_id;
      if (!sensors[sensorId]) {
        sensors[sensorId] = {
          temperature: new Map(),
          humidity: new Map()
        };
      }
      sensors[sensorId].temperature.set(entry.timestamp, entry.temperatura);
      sensors[sensorId].humidity.set(entry.timestamp, entry.humedad);
    });

    const colors = [
      'rgba(255, 99, 132, 0.7)',
      'rgba(54, 162, 235, 0.7)',
      'rgba(255, 206, 86, 0.7)',
      'rgba(75, 192, 192, 0.7)',
      'rgba(153, 102, 255, 0.7)'
    ];

    // Crear datasets para temperatura
    const tempDatasets = [];
    let sensorIndex = 0;
    for (const sensorId in sensors) {
      const sensorData = sensors[sensorId].temperature;
      const sensorTempArray = uniqueTimestamps.map(timestamp => sensorData.get(timestamp) || null);
      tempDatasets.push({
        label: `Line ${sensorId}`,
        data: sensorTempArray,
        backgroundColor: colors[sensorIndex % colors.length],
        borderColor: colors[sensorIndex % colors.length],
        fill: false,
        tension: 0.1,
        pointBackgroundColor: sensorTempArray.map(value =>
          value !== null && value >= 10 && value <= 30
            ? colors[sensorIndex % colors.length]
            : 'rgba(255, 0, 0, 1)'
        ),
        pointRadius: 5
      });
      sensorIndex++;
    }

    // Crear datasets para humedad
    const humidityDatasets = [];
    sensorIndex = 0;
    for (const sensorId in sensors) {
      const sensorData = sensors[sensorId].humidity;
      const sensorHumidityArray = uniqueTimestamps.map(timestamp => sensorData.get(timestamp) || null);
      humidityDatasets.push({
        label: `Line ${sensorId}`,
        data: sensorHumidityArray,
        backgroundColor: colors[sensorIndex % colors.length],
        borderColor: colors[sensorIndex % colors.length],
        fill: false,
        tension: 0.1,
        pointBackgroundColor: sensorHumidityArray.map(value =>
          value !== null && value >= 30 && value <= 75
            ? colors[sensorIndex % colors.length]
            : 'rgba(255, 0, 0, 1)'
        ),
        pointRadius: 5
      });
      sensorIndex++;
    }

    // Calcular promedio por timestamp (usando solo filteredData)
    const averageTempPerTimestamp = uniqueTimestamps.map(timestamp => {
      const temps = filteredData
        .filter(entry => entry.timestamp === timestamp && entry.temperatura !== null)
        .map(entry => parseFloat(entry.temperatura));
      const avg = temps.length ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
      return avg !== null ? parseFloat(avg.toFixed(2)) : null;
    });

    const averageHumidityPerTimestamp = uniqueTimestamps.map(timestamp => {
      const hums = filteredData
        .filter(entry => entry.timestamp === timestamp && entry.humedad !== null)
        .map(entry => parseFloat(entry.humedad));
      const avg = hums.length ? hums.reduce((a, b) => a + b, 0) / hums.length : null;
      return avg !== null ? parseFloat(avg.toFixed(2)) : null;
    });

    // Añadir dataset para el promedio
    tempDatasets.push({
      label: 'Promedio',
      data: averageTempPerTimestamp,
      backgroundColor: 'rgba(0, 255, 0, 0.5)',
      borderColor: 'rgba(0, 255, 0, 1)',
      fill: false,
      tension: 0.1,
      borderWidth: 2,
      pointRadius: 0,
      borderDash: []
    });

    humidityDatasets.push({
      label: 'Promedio',
      data: averageHumidityPerTimestamp,
      backgroundColor: 'rgba(0, 255, 0, 0.5)',
      borderColor: 'rgba(0, 255, 0, 1)',
      fill: false,
      tension: 0.1,
      borderWidth: 2,
      pointRadius: 0,
      borderDash: []
    });

    // Líneas de mínimo y máximo para temperatura
    tempDatasets.push({
      label: 'Mínimo (10°C)',
      data: uniqueTimestamps.map(() => 10),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    });

    tempDatasets.push({
      label: 'Máximo (30°C)',
      data: uniqueTimestamps.map(() => 30),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    });

    // Líneas de mínimo y máximo para humedad
    humidityDatasets.push({
      label: 'Mínimo (30%)',
      data: uniqueTimestamps.map(() => 30),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    });

    humidityDatasets.push({
      label: 'Máximo (75%)',
      data: uniqueTimestamps.map(() => 75),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    });

    generateOrUpdateChart(
      'temperatureChart',
      'Temperatura (°C)',
      labels,
      tempDatasets,
      5,
      40,
      temperatureChartInstance,
      chart => { temperatureChartInstance = chart; }
    );
    generateOrUpdateChart(
      'humidityChart',
      'Humedad (%)',
      labels,
      humidityDatasets,
      15,
      80,
      humidityChartInstance,
      chart => { humidityChartInstance = chart; }
    );

    updateAverageCardColors(overallTemp, overallHumidity);
  } catch (error) {
    console.error('Error fetching data:', error);
  }
}

function generateOrUpdateChart(canvasId, label, labels, datasets, minY, maxY, existingChart, setChartInstance) {
  const ctx = document.getElementById(canvasId).getContext('2d');
  if (existingChart) {
    existingChart.data.labels = labels;
    existingChart.data.datasets = datasets;
    existingChart.options.scales.y.min = minY;
    existingChart.options.scales.y.max = maxY;
    existingChart.update();
  } else {
    const newChart = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#ffffff' } },
          tooltip: { enabled: true, backgroundColor: '#333', titleColor: '#fff', bodyColor: '#fff' }
        },
        scales: {
          x: { ticks: { color: '#ffffff' }, grid: { color: '#444' } },
          y: { min: minY, max: maxY, ticks: { stepSize: 5, color: '#ffffff' }, grid: { color: '#444' } }
        }
      }
    });
    setChartInstance(newChart);
  }
}

function formatTimestamp(timestamp) {
  const date = new Date(timestamp);
  const hours = date.getHours().toString().padStart(2, '0');
  const minutes = date.getMinutes().toString().padStart(2, '0');
  return `${hours}:${minutes}`;
}

async function sendAlertEmail(type, value, unit, min, max) {
  try {
    const response = await fetch('../send_alerts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, value, unit, min, max })
    });
    const result = await response.json();
    if (result.status !== 'success') {
      console.error('Error al enviar el correo:', result.message);
    } else {
      console.log('Correo de alerta enviado correctamente.');
    }
  } catch (error) {
    console.error('Error al enviar el correo:', error);
  }
}

function updateAverageCardColors(avgTemp, avgHumidity) {
  const tempCard = document.getElementById('avg-temp-card');
  const humidityCard = document.getElementById('avg-humidity-card');
  const body = document.body;

  tempCard.classList.remove('green', 'yellow', 'red');
  humidityCard.classList.remove('green', 'yellow', 'red');

  let tempStatus = '';
  if (avgTemp < 10 || avgTemp > 30) {
    tempStatus = 'red';
    sendAlertEmail('Temperatura', avgTemp, 'Temperatura', 10, 30);
  } else if ((avgTemp >= 10 && avgTemp < 12) || (avgTemp > 28 && avgTemp <= 30)) {
    tempStatus = 'yellow';
  } else {
    tempStatus = 'green';
  }

  let humidityStatus = '';
  if (avgHumidity < 30 || avgHumidity > 75) {
    humidityStatus = 'red';
    sendAlertEmail('Humedad', avgHumidity, 'Humedad', 30, 75);
  } else if ((avgHumidity >= 30 && avgHumidity < 34.5) || (avgHumidity > 70.5 && avgHumidity <= 75)) {
    humidityStatus = 'yellow';
  } else {
    humidityStatus = 'green';
  }

  tempCard.classList.add(tempStatus);
  humidityCard.classList.add(humidityStatus);

  let globalStatus = 'green';
  if (tempStatus === 'red' || humidityStatus === 'red') {
    globalStatus = 'red';
  } else if (tempStatus === 'yellow' || humidityStatus === 'yellow') {
    globalStatus = 'yellow';
  }

  switch (globalStatus) {
    case 'green':
      console.log('Cambiando fondo a verde');
      body.style.backgroundImage = "url('../css/fondo_verde.png')";
      break;
    case 'yellow':
      console.log('Cambiando fondo a amarillo');
      body.style.backgroundImage = "url('../css/fondo_amarillo.png')";
      break;
    case 'red':
      console.log('Cambiando fondo a rojo');
      body.style.backgroundImage = "url('../css/fondo_rojo.png')";
      break;
    default:
      console.log('Estado desconocido, fondo no cambiado');
      body.style.backgroundImage = "none";
  }
}

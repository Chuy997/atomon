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

    // Obtener el último valor de cada sensor
    const lastValues = {};
    filteredData.forEach(entry => {
      const sensorId = entry.sensor_id;
      if (!lastValues[sensorId]) {
        lastValues[sensorId] = {
          temperatura: parseFloat(entry.temperatura),
          humedad: parseFloat(entry.humedad)
        };
      }
    });

    // Calcular promedios generales
    const tempValues = Object.values(lastValues)
      .filter(entry => !isNaN(entry.temperatura))
      .map(entry => entry.temperatura);
    const overallTemp = tempValues.length
      ? parseFloat((tempValues.reduce((a, b) => a + b, 0) / tempValues.length).toFixed(2))
      : '--';

    const humidityValues = Object.values(lastValues)
      .filter(entry => !isNaN(entry.humedad))
      .map(entry => entry.humedad);
    const overallHumidity = humidityValues.length
      ? parseFloat((humidityValues.reduce((a, b) => a + b, 0) / humidityValues.length).toFixed(2))
      : '--';

    document.getElementById('avg-temperature').innerText = `${overallTemp} °C`;
    document.getElementById('avg-humidity').innerText = `${overallHumidity} %`;

    // Preparar datos para las gráficas de barras
    const sensorIds = Object.keys(lastValues);
    const colors = [
      'rgba(255, 99, 132, 0.7)',
      'rgba(54, 162, 235, 0.7)',
      'rgba(255, 206, 86, 0.7)',
      'rgba(75, 192, 192, 0.7)',
      'rgba(153, 102, 255, 0.7)'
    ];

    const tempDatasets = [{
      label: 'Última Temperatura',
      data: sensorIds.map(id => lastValues[id].temperatura),
      backgroundColor: colors,
      borderColor: colors.map(color => color.replace('0.7', '1')),
      borderWidth: 1
    }];

    const humidityDatasets = [{
      label: 'Última Humedad',
      data: sensorIds.map(id => lastValues[id].humedad),
      backgroundColor: colors,
      borderColor: colors.map(color => color.replace('0.7', '1')),
      borderWidth: 1
    }];

    // Líneas de mínimo y máximo para temperatura
    tempDatasets.push({
      label: 'Mínimo (10°C)',
      data: sensorIds.map(() => 10),
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0,
      type: 'line' // Asegurando que sea una línea horizontal
    });

    tempDatasets.push({
      label: 'Máximo (30°C)',
      data: sensorIds.map(() => 30),
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0,
      type: 'line' // Asegurando que sea una línea horizontal
    });

    // Líneas de mínimo y máximo para humedad
    humidityDatasets.push({
      label: 'Mínimo (30%)',
      data: sensorIds.map(() => 30),
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0,
      type: 'line' // Asegurando que sea una línea horizontal
    });

    humidityDatasets.push({
      label: 'Máximo (75%)',
      data: sensorIds.map(() => 75),
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0,
      type: 'line' // Asegurando que sea una línea horizontal
    });

    generateOrUpdateChart(
      'temperatureChart',
      'Temperatura (°C)',
      sensorIds.map(id => `Sensor ${id}`),
      tempDatasets,
      5,
      40,
      temperatureChartInstance,
      chart => { temperatureChartInstance = chart; }
    );

    generateOrUpdateChart(
      'humidityChart',
      'Humedad (%)',
      sensorIds.map(id => `Sensor ${id}`),
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
      type: 'bar',
      data: { labels, datasets },
      options: {        
        responsive: true,
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
}

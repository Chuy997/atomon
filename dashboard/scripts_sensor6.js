// scripts_sensor6.js

let temperatureChartInstance = null;
let humidityChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
  fetchData();
  // Actualización automática cada 2 minutos (120000 ms)
  setInterval(fetchData, 2 * 60 * 1000);
});

async function fetchData() {
  try {
    const response = await fetch('../get_data.php');
    const result = await response.json();

    console.log('Datos recibidos:', result);

    if (result.status !== 'success') {
      alert(`Error: ${result.message}`);
      console.error('Respuesta del servidor:', result);
      return;
    }

    const data = result.data;

    // Filtrar datos para quedarnos solo con el sensor con id 6
    const filteredData = data.filter(entry => parseInt(entry.sensor_id, 10) === 6);
    console.log('Datos filtrados (sensor 6):', filteredData);

    // Calcular promedios generales para las tarjetas
    const now = new Date();
        const fifteenMinutesAgo = new Date(now.getTime() - 15 * 60 * 1000);
        const recentData = filteredData.filter(entry => new Date(entry.timestamp) >= fifteenMinutesAgo);

    let overallTemp, overallHumidity;
    const tempValues = filteredData
      .filter(entry => entry.temperatura !== null)
      .map(entry => entry.temperatura);
    overallTemp = tempValues.length ? parseFloat((tempValues.reduce((a, b) => a + b, 0) / tempValues.length).toFixed(2)) : '--';
    
    const humidityValues = filteredData
      .filter(entry => entry.humedad !== null)
      .map(entry => entry.humedad);
    overallHumidity = humidityValues.length ? parseFloat((humidityValues.reduce((a, b) => a + b, 0) / humidityValues.length).toFixed(2)) : '--';

    document.getElementById('avg-temperature').innerText = `${overallTemp} °C`;
    document.getElementById('avg-humidity').innerText = `${overallHumidity} %`;

     // Verificar si la temperatura está fuera de rango
     if (overallTemp !== '--' && (overallTemp < 10 || overallTemp > 30)) {
      console.log(`Temperatura fuera de rango: ${overallTemp} °C`);
      sendTemperatureAlert(overallTemp); // Enviar correo de alerta
    }
    // Ordenar los datos filtrados por timestamp de forma ascendente
    filteredData.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));

    // Generar etiquetas de tiempo únicas y ordenadas
    const uniqueTimestamps = [...new Set(filteredData.map(entry => entry.timestamp))];
    uniqueTimestamps.sort((a, b) => new Date(a) - new Date(b));
    const labels = uniqueTimestamps.map(timestamp => formatTimestamp(timestamp));
    console.log('Labels:', labels);

    // Como solo se tiene un sensor (id 6), creamos los datasets directamente
    const sensorTempArray = uniqueTimestamps.map(timestamp => {
      const entry = filteredData.find(e => e.timestamp === timestamp);
      return entry ? entry.temperatura : null;
    });
    const sensorHumidityArray = uniqueTimestamps.map(timestamp => {
      const entry = filteredData.find(e => e.timestamp === timestamp);
      return entry ? entry.humedad : null;
    });

    // Opcional: definir un color para el sensor 6
    const sensorColor = 'rgba(54, 162, 235, 0.7)'; // Azul

    // Dataset para temperatura
    const tempDataset = {
      label: 'Sensor 6',
      data: sensorTempArray,
      backgroundColor: sensorColor,
      borderColor: sensorColor,
      fill: false,
      tension: 0.1,
      pointBackgroundColor: sensorTempArray.map(value =>
        value !== null && value >= 10 && value <= 30 ? sensorColor : 'rgba(255, 0, 0, 1)'
      ),
      pointRadius: 5
    };

    // Dataset para humedad
    const humidityDataset = {
      label: 'Sensor 6',
      data: sensorHumidityArray,
      backgroundColor: sensorColor,
      borderColor: sensorColor,
      fill: false,
      tension: 0.1,
      pointBackgroundColor: sensorHumidityArray.map(value =>
        value !== null && value >= 30 && value <= 75 ? sensorColor : 'rgba(255, 0, 0, 1)'
      ),
      pointRadius: 5
    };

    // Calcular promedio por timestamp (aunque en este caso, cada timestamp corresponde a un solo registro)
    const averageTempPerTimestamp = uniqueTimestamps.map(timestamp => {
      const temps = filteredData
        .filter(entry => entry.timestamp === timestamp && entry.temperatura !== null)
        .map(entry => entry.temperatura);
      const avg = temps.length ? (temps.reduce((a, b) => a + b, 0) / temps.length) : null;
      return avg !== null ? parseFloat(avg.toFixed(2)) : null;
    });
    const averageHumidityPerTimestamp = uniqueTimestamps.map(timestamp => {
      const hums = filteredData
        .filter(entry => entry.timestamp === timestamp && entry.humedad !== null)
        .map(entry => entry.humedad);
      const avg = hums.length ? (hums.reduce((a, b) => a + b, 0) / hums.length) : null;
      return avg !== null ? parseFloat(avg.toFixed(2)) : null;
    });

    // Dataset para el promedio (opcional, para visualizar la tendencia)
    const tempAverageDataset = {
      label: 'Promedio',
      data: averageTempPerTimestamp,
      backgroundColor: 'rgba(0, 255, 0, 0.5)',
      borderColor: 'rgba(0, 255, 0, 1)',
      fill: false,
      tension: 0.1,
      borderWidth: 2,
      pointRadius: 0
    };
    const humidityAverageDataset = {
      label: 'Promedio',
      data: averageHumidityPerTimestamp,
      backgroundColor: 'rgba(0, 255, 0, 0.5)',
      borderColor: 'rgba(0, 255, 0, 1)',
      fill: false,
      tension: 0.1,
      borderWidth: 2,
      pointRadius: 0
    };
    
    // Puedes agregar líneas de máximo y mínimo si lo deseas, de la misma forma que en el script original
    const tempMinDataset = {
      label: 'Mínimo (10°C)',
      data: uniqueTimestamps.map(() => 10),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    };
    const tempMaxDataset = {
      label: 'Máximo (30°C)',
      data: uniqueTimestamps.map(() => 30),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    };

    const humidityMinDataset = {
      label: 'Mínimo (30%)',
      data: uniqueTimestamps.map(() => 30),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    };
    const humidityMaxDataset = {
      label: 'Máximo (75%)',
      data: uniqueTimestamps.map(() => 75),
      backgroundColor: 'rgba(0, 0, 255, 0.3)',
      borderColor: 'rgba(0, 0, 255, 1)',
      borderDash: [5, 5],
      fill: false,
      tension: 0,
      pointRadius: 0
    };

    // Conformar los datasets para cada gráfico
    const tempDatasets = [tempDataset, tempAverageDataset, tempMinDataset, tempMaxDataset];
    const humidityDatasets = [humidityDataset, humidityAverageDataset, humidityMinDataset, humidityMaxDataset];

    console.log('Datasets de Temperatura:', tempDatasets);
    console.log('Datasets de Humedad:', humidityDatasets);

    // Generar o actualizar los gráficos
    generateOrUpdateChart('temperatureChart', 'Temperatura (°C)', labels, tempDatasets, 5, 40, temperatureChartInstance, chart => {
      temperatureChartInstance = chart;
    });
    generateOrUpdateChart('humidityChart', 'Humedad (%)', labels, humidityDatasets, 15, 80, humidityChartInstance, chart => {
      humidityChartInstance = chart;
    });

    // Actualizar colores de las tarjetas de promedio
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
      data: {
        labels: labels,
        datasets: datasets
      },
      options: {
        responsive: true,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            labels: {
              color: '#ffffff'
            }
          },
          tooltip: {
            enabled: true,
            backgroundColor: '#333',
            titleColor: '#fff',
            bodyColor: '#fff',
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#ffffff'
            },
            grid: {
              color: '#444'
            }
          },
          y: {
            min: minY,
            max: maxY,
            ticks: {
              stepSize: 5,
              color: '#ffffff'
            },
            grid: {
              color: '#444'
            }
          }
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

function updateAverageCardColors(avgTemp, avgHumidity) {
  const tempCard = document.getElementById('avg-temp-card');
  const humidityCard = document.getElementById('avg-humidity-card');

  // Resetear clases
  tempCard.classList.remove('green', 'yellow', 'red');
  humidityCard.classList.remove('green', 'yellow', 'red');

  let tempStatus = '';
  if (avgTemp < 10 || avgTemp > 30) {
    tempStatus = 'red';
  } else if ((avgTemp >= 10 && avgTemp < 12) || (avgTemp > 28 && avgTemp <= 30)) {
    tempStatus = 'yellow';
  } else {
    tempStatus = 'green';
  }

  let humidityStatus = '';
  if (avgHumidity < 30 || avgHumidity > 75) {
    humidityStatus = 'red';
  } else if ((avgHumidity >= 30 && avgHumidity < 34.5) || (avgHumidity > 70.5 && avgHumidity <= 75)) {
    humidityStatus = 'yellow';
  } else {
    humidityStatus = 'green';
  }

  tempCard.classList.add(tempStatus);
  humidityCard.classList.add(humidityStatus);
}
// Función para enviar correo de alerta
function sendTemperatureAlert(temperature) {
  const xhr = new XMLHttpRequest();
  xhr.open('POST', '../send_alert.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4 && xhr.status === 200) {
      console.log('Correo enviado correctamente.');
    } else if (xhr.readyState === 4) {
      console.error('Error al enviar el correo:', xhr.responseText);
    }
  };

  const data = JSON.stringify({ temperature });
  xhr.send(data);
}
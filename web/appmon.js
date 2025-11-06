// Client-side JS for Chart.js rendering

const charts = {};

async function fetchMetric(metricKey, minutes){
  const url = `apimon.php?action=data&metric=${encodeURIComponent(metricKey)}&minutes=${minutes}`;
  const res = await fetch(url);
  if(!res.ok){ throw new Error('API error'); }
  const data = await res.json();
  for(const p of data.points){
    const str = p.t;
    const jsDate = new Date(str.replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1'));
    p.t = jsDate;
  }
  //Data contains also unit
  return data;
}

function lineDataset(label, color, data, unit){
  return {
    label, data, unit,
    parsing: {xAxisKey: 't', yAxisKey: 'v'},
    borderColor: color,
    backgroundColor: color + '40',
    tension: 0.2,
    pointRadius: 0,
  };
}

function ensureChart(canvasId, label, datasets){
  console.log("Datasets: ", datasets)
  const ctx = document.getElementById(canvasId).getContext('2d');
  if(charts[canvasId]){
    const c = charts[canvasId];
    c.data.datasets = datasets;
    c.options.plugins.title.text = label;
    c.options.plugins.title.display = true;
    c.update();
    return c;
  }
  const c = new Chart(ctx, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#e8eef7' } },
        title: {
          display: true,
          text: label,
          align: 'start',
          font: {
            size: 18,
            weight: 'bold'},
          color: '#e8eef7' },
        tooltip: {
          mode: 'index',
          intersect: false,
          callbacks: {
            label: function(context) {
              const unit = context.dataset.unit || '';
              const value = context.parsed.y;
              const rounded = typeof value === 'number' ? value.toFixed(1) : value;
              return `${context.dataset.label}: ${rounded} ${unit}`;
              }
            }
          }
        },
      scales: {
        x: {
          type: 'time',
          time: {
            tooltipFormat: 'yyyy-MM-dd HH:mm:ss',
           displayFormats: {
             hour: 'dd-MMM HH:mm',
             minute: 'dd-MMM HH:mm',
             day: 'dd-MMM HH:mm' } },
          ticks: {
            color: '#98a2b3',
            callback: function(value, index, ticks) { const dt = luxon.DateTime.fromMillis(value); return dt.toFormat('dd-MMM HH:mm').toUpperCase();},
            maxRotation: 45,
            minRotation: 45
            },
          grid:{color:'#1f2630'} },
        y: { ticks: { color: '#98a2b3' }, grid:{color:'#1f2630'} }
      }
    }
  });
  charts[canvasId] = c;
  return c;
}

const loadMetrics = [];

function addMetricToLoadAll(config) {
  loadMetrics.push(config);
}

async function loadAll(){
  const minutes = document.getElementById('range').value;
  const host = document.getElementById('device') ? document.getElementById('device').value : '';

  for(const m of loadMetrics) {
    try {
      const datasets = [];
      let hasData = false;
      for(const s of m.series) {
        const metricKey = typeof s.metric === 'function' ? s.metric(host) : s.metric;
        if (!metricKey.startsWith(host + '.')) continue;
        const data = await fetchMetric(metricKey, minutes);
        if (data.points && data.points.length > 0) {
          hasData = true;
        }
        datasets.push(lineDataset(s.label || data.name, s.color, data.points, data.unit));
      }
     const card = document.getElementById(m.canvasId)?.closest('.card');
      if (hasData && datasets.length > 0) {
        ensureChart(m.canvasId, m.label, datasets);
       if (card) card.style.display = '';
      } else {
        if (card) card.style.display = 'none';
      }
    } catch(e) {
      const card = document.getElementById(m.canvasId)?.closest('.card');
      if (card) card.style.display = 'none';
      console.error(e);
    }
  }
}

function setup(){
  //menu selectors change
  document.getElementById('autorefresh').addEventListener('click', loadAll);
  document.getElementById('device').addEventListener('change', loadAll);
  document.getElementById('device').dispatchEvent(new Event('change'));
  document.getElementById('range').addEventListener('change', loadAll);
  document.getElementById('range').dispatchEvent(new Event('change'));
  //Autorefresh checkbox
  const checkbox = document.getElementById('autorefresh');
  const refreshInterval = parseInt(checkbox.dataset.interval, 10) * 1000;
  let timer = setInterval(loadAll, refreshInterval);
  checkbox.addEventListener('change', ()=>{
    if(checkbox.checked){ timer = setInterval(loadAll, refreshInterval); } else { clearInterval(timer); }
  });
  loadAll();
}

document.addEventListener('DOMContentLoaded', setup);

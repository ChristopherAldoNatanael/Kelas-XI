import Chart from 'chart.js/auto';

// Hanya dashboard yang membutuhkan Chart.js. Entry terpisah menjaga halaman
// admin lain tetap ringan (Chart.js tidak ikut di bundle global app.js).
window.Chart = Chart;

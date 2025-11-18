var ssmptmsHourStatsCharts = {};

function ssmptmsCreateHourStatsChart(canvas_id, data) {
    const canvas = document.getElementById(canvas_id);
    if (!canvas) return;

    const existingChart = ssmptmsHourStatsCharts[canvas_id];
    if (existingChart) existingChart.destroy();

    const chart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels.map(d => new Date(d)),
            datasets: [{
                data: data.values,
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'HH:mm'
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    ssmptmsHourStatsCharts[canvas_id] = chart;
}



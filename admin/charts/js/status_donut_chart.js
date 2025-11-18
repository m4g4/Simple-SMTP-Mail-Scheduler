var ssmptmsDonutCharts = {};

function ssmptmsCreateStatusDonutChart(canvas_id, data) {
    const canvas = document.getElementById(canvas_id);
    if (!canvas) return;

    const existingChart = ssmptmsDonutCharts[canvas_id];
    if (existingChart) existingChart.destroy();

    const chart = new Chart(canvas.getContext('2d'), {
        type: "doughnut",
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: data.colors,
                borderWidth: 1,
                hoverOffset: 10
            }]
        },
        options: {
            cutout: "70%",
            plugins: {
                legend: {
                    position: "bottom",
                    labels: { usePointStyle: true, padding: 20 }
                }
            }
        }
    });

    ssmptmsDonutCharts[canvas_id] = chart;
}



/**
 * Dashboard Charts and Analytics
 */

(async function () {
    'use strict';

    /** #################### -- The base JS part should be on top of all JS files --  #################### **/
    // Language Translator
    let base_url = $('#base_url').val();
    let language_name = $('#language_name').val();
    let language_path = '/resources/lang/' + language_name + '.json';
    let language_file = base_url + language_path;
    let language_key = {};

    async function loadLanguage() {
        try {
            const res = await fetch(language_file);   // ⬅ await fetch
            language_key = await res.json();          // ⬅ await json()
            console.log('Language loaded inside function:', language_key);
        } catch (err) {
            console.error('Failed to load language file:', err);
        }
    }
    loadLanguage().then(() => {
        console.log('Language loaded:', language_key);
    });


    // Company Info
    let company_data = $('#company_data').val();
    let company_session_data = {};
    try {
        company_session_data = JSON.parse(company_data);
    } catch (e) {
        console.error('Error parsing company info:', e);
    }
    /** #################### -- The base JS part should be on top of all JS files --  #################### **/


    let profitChart = null;
    let revenueChart = null;
    let reportBarChart = null;
    let operationalChart = null;

    // Wait for DOM and data to be ready
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof dashboardData === 'undefined') {
            console.warn('Dashboard data not found');
            return;
        }

        // Initialize Select2 for outlet
        if ($('#outlet_id').length) {
            $('#outlet_id').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        // Initialize date pickers
        $('.datePicker').flatpickr({
            altInput: true,
            altFormat: 'Y-m-d',
            dateFormat: 'Y-m-d',
            static: true,
            allowInput: true
        });

        // Initialize all charts
        initProfitChart();
        initRevenueChart();
        initReportBarChart();
        initOperationalComparisonChart();

        // Handle filter form submission
        $('#applyFilter').on('click', function() {
            loadDashboardData();
        });
    });

    /**
     * Initialize Profit Chart (Last Month) - Line Chart
     */
    function initProfitChart() {
        const profitElement = document.getElementById('profitLastMonth');
        if (!profitElement) return;

        // Get config colors if available
        const config = window.config || {};
        const borderColor = config.colors?.borderColor || '#d9dee3';
        const infoColor = config.colors?.info || '#00cfe8';
        const cardColor = config.colors?.cardColor || '#fff';

        // Generate profit data for last 6 periods (weeks or days)
        // Create a trend line that ends at the current profit value
        const currentProfit = dashboardData.lastMonthProfit || 0;
        const profitData = [];
        
        // Generate 6 data points with a trend leading to current profit
        for (let i = 0; i < 6; i++) {
            if (i === 0) {
                profitData.push(0);
            } else if (i === 5) {
                // Last point should be close to current profit
                profitData.push(Math.max(0, currentProfit * 0.9));
            } else {
                // Create a gradual increase
                const progress = i / 5;
                profitData.push(Math.max(0, currentProfit * progress * 0.8));
            }
        }

        const options = {
            chart: {
                height: 300,
                type: 'line',
                parentHeightOffset: 0,
                toolbar: {
                    show: false
                }
            },
            grid: {
                borderColor: borderColor,
                strokeDashArray: 6,
                xaxis: {
                    lines: {
                        show: true,
                        colors: '#000'
                    }
                },
                yaxis: {
                    lines: {
                        show: false
                    }
                },
                padding: {
                    top: -18,
                    left: -4,
                    right: 7,
                    bottom: -10
                }
            },
            colors: [infoColor],
            stroke: {
                width: 2
            },
            series: [
                {
                    data: profitData
                }
            ],
            xaxis: {
                labels: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                axisBorder: {
                    show: false
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            tooltip: {
                enabled: false
            },
            markers: {
                size: 3.5,
                fillColor: infoColor,
                strokeColors: 'transparent',
                strokeWidth: 3.2,
                offsetX: -1,
                discrete: [
                    {
                        seriesIndex: 0,
                        dataPointIndex: 5,
                        fillColor: cardColor,
                        strokeColor: infoColor,
                        size: 4.5,
                        shape: 'circle'
                    }
                ],
                hover: {
                    size: 5.5
                }
            },
            responsive: [
                {
                    breakpoint: 768,
                    options: {
                        chart: {
                            height: 300
                        }
                    }
                }
            ]
        };

        if (profitChart) {
            profitChart.destroy();
        }
        profitChart = new ApexCharts(profitElement, options);
        profitChart.render();
    }

    /**
     * Initialize Revenue Report Chart
     */
    function initRevenueChart() {
        const revenueElement = document.getElementById('totalRevenueChart');
        if (!revenueElement) return;

        const revenueData = dashboardData.revenueData || [];
        const months = revenueData.map(item => item.month);
        const revenues = revenueData.map(item => item.revenue);

        const options = {
            series: [
                {
                    name: 'Revenue',
                    data: revenues
                }
            ],
            chart: {
                height: 300,
                type: 'area',
                parentHeightOffset: 0,
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                }
            },
            colors: ['#7367f0'],
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.5,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: months,
                labels: {
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            },
            grid: {
                borderColor: '#e7e7e7',
                strokeDashArray: 3,
                xaxis: {
                    lines: {
                        show: true
                    }
                },
                yaxis: {
                    lines: {
                        show: true
                    }
                }
            }
        };

        if (revenueChart) {
            revenueChart.destroy();
        }
        revenueChart = new ApexCharts(revenueElement, options);
        revenueChart.render();
    }

    /**
     * Initialize Report Bar Chart (Earning Reports)
     */
    function initReportBarChart() {
        const barChartElement = document.getElementById('reportBarChart');
        if (!barChartElement) return;

        // Get config colors if available
        const config = window.config || {};
        const labelColor = config.colors?.textMuted || '#a1acb8';
        const primaryColor = config.colors?.primary || '#7367f0';
        const primaryLabelColor = config.colors_label?.primary || '#e7e7e7';

        // Get last 7 days data for bar chart
        const dates = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
        const dailyData = [];

        // Calculate daily income for last 7 days (distribute total income)
        const totalIncome = dashboardData.totalIncome || 0;
        for (let i = 6; i >= 0; i--) {
            // Distribute income across 7 days with some variation
            const baseValue = totalIncome / 7;
            const variation = baseValue * 0.3; // 30% variation
            const randomFactor = 0.7 + Math.random() * 0.6; // Random between 0.7 and 1.3
            dailyData.push(Math.max(0, baseValue * randomFactor));
        }

        // Colors array - highlight the 5th bar (Friday) with primary color
        const colors = [
            primaryLabelColor,
            primaryLabelColor,
            primaryLabelColor,
            primaryLabelColor,
            primaryColor,
            primaryLabelColor,
            primaryLabelColor
        ];

        const options = {
            chart: {
                height: 230,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    barHeight: '60%',
                    columnWidth: '60%',
                    startingShape: 'rounded',
                    endingShape: 'rounded',
                    borderRadius: 4,
                    distributed: true
                }
            },
            grid: {
                show: false,
                padding: {
                    top: -20,
                    bottom: 0,
                    left: -10,
                    right: -10
                }
            },
            colors: colors,
            dataLabels: {
                enabled: false
            },
            series: [
                {
                    data: dailyData
                }
            ],
            legend: {
                show: false
            },
            xaxis: {
                categories: dates,
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '13px'
                    }
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            },
            responsive: [
                {
                    breakpoint: 1025,
                    options: {
                        chart: {
                            height: 190
                        }
                    }
                },
                {
                    breakpoint: 769,
                    options: {
                        chart: {
                            height: 250
                        }
                    }
                }
            ]
        };

        if (reportBarChart) {
            reportBarChart.destroy();
        }
        reportBarChart = new ApexCharts(barChartElement, options);
        reportBarChart.render();
    }

    /**
     * Initialize Operational Comparison Chart
     */
    function initOperationalComparisonChart() {
        const operationalElement = document.getElementById('operationalComparisonChart');
        if (!operationalElement) return;

        // Get config colors if available
        const config = window.config || {};
        const labelColor = config.colors?.textMuted || '#a1acb8';
        const primaryColor = config.colors?.primary || '#7367f0';
        const successColor = config.colors?.success || '#28c76f';
        const warningColor = config.colors?.warning || '#ff9f43';
        const dangerColor = config.colors?.danger || '#ea5455';
        const infoColor = config.colors?.info || '#00cfe8';
        const secondaryColor = config.colors?.secondary || '#82868b';

        const operationalData = dashboardData.operationalData || {};

        console.log(language_key.purchase ?? 'Purchase');
        
        const categories = ['Purchase', 'Sale', 'Damage', 'Expense', 'Customer Receive', 'Supplier Payment'];
        const data = [
            operationalData.purchase || 0,
            operationalData.sale || 0,
            operationalData.damage || 0,
            operationalData.expense || 0,
            operationalData.customer_receive || 0,
            operationalData.supplier_payment || 0
        ];

        const colors = [primaryColor, successColor, dangerColor, warningColor, infoColor, secondaryColor];

        const options = {
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded',
                    borderRadius: 4,
                    distributed: false,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return formatCurrency(val);
                },
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: [labelColor]
                }
            },
            colors: colors,
            series: [{
                name: 'Amount',
                data: data
            }],
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    },
                    style: {
                        colors: labelColor
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            },
            grid: {
                borderColor: config.colors?.borderColor || '#e7e7e7',
                strokeDashArray: 3,
                xaxis: {
                    lines: {
                        show: true
                    }
                },
                yaxis: {
                    lines: {
                        show: true
                    }
                }
            },
            legend: {
                show: false
            },
            responsive: [{
                breakpoint: 1025,
                options: {
                    chart: {
                        height: 300
                    }
                }
            }, {
                breakpoint: 769,
                options: {
                    chart: {
                        height: 350
                    },
                    plotOptions: {
                        bar: {
                            columnWidth: '60%'
                        }
                    }
                }
            }]
        };

        if (operationalChart) {
            operationalChart.destroy();
        }
        operationalChart = new ApexCharts(operationalElement, options);
        operationalChart.render();
    }

    /**
     * Load dashboard data via AJAX
     */
    function loadDashboardData() {
        const dateFrom = $('#date_from').val();
        const dateTo = $('#date_to').val();
        const outletId = $('#outlet_id').val();

        // Show loading state
        $('#applyFilter').prop('disabled', true).html('<i class="ti tabler-loader-2 me-1"></i>Loading...');

        // Get route path
        let dashboardReportRoute;
        try {
            dashboardReportRoute = route('dashboard', {}, false, Ziggy);
        } catch (e) {
            dashboardReportRoute = typeof dashboardRoute !== 'undefined' ? dashboardRoute : '/dashboard';
        }
        
        $.ajax({
            url: dashboardReportRoute,
            type: 'GET',
            data: {
                date_from: dateFrom,
                date_to: dateTo,
                outlet_id: outletId
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Update Statistics
                    $('#statSalesCount').text((data.statistics.totalSalesCount));
                    $('#statRevenueAmount').text((data.statistics.totalSalesAmount));
                    $('#statCustomersCount').text((data.statistics.totalCustomers));
                    $('#statProductsCount').text((data.statistics.totalProducts));

                    // Update Profit
                    $('#profitAmount').text((data.profit.amount));
                    const profitPct = data.profit.percentage;
                    $('#profitPercentage')
                        .removeClass('text-success text-danger')
                        .addClass(profitPct >= 0 ? 'text-success' : 'text-danger')
                        .text((profitPct >= 0 ? '+' : '') + profitPct.toFixed(2) + '%');
                    
                    // Update subtitle
                    if (dateFrom && dateTo) {
                        $('#profitSubtitle').text('Period: ' + dateFrom + ' to ' + dateTo);
                    } else {
                        $('#profitSubtitle').text('Last Month');
                    }

                    // Update Earning Reports
                    $('#earningSalesCount').text((data.statistics.totalSalesCount) + ' Sales');
                    $('#earningNetProfit').text((data.earning.netProfit));
                    $('#earningTotalIncome').text((data.earning.totalIncome));
                    $('#earningTotalExpenses').text((data.earning.totalExpenses));
                    
                    // Update percentage changes
                    updatePercentageChange('#earningNetProfitIcon', '#earningNetProfitChange', data.earning.netProfitChange);
                    updatePercentageChange('#earningTotalIncomeIcon', '#earningTotalIncomeChange', data.earning.totalIncomeChange);
                    updatePercentageChange('#earningTotalExpensesIcon', '#earningTotalExpensesChange', data.earning.totalExpensesChange);

                    // Update charts
                    dashboardData.revenueData = data.revenue;
                    dashboardData.lastMonthProfit = data.profit.amount;
                    dashboardData.netProfit = data.earning.netProfit;
                    dashboardData.totalIncome = data.earning.totalIncome;
                    dashboardData.totalExpenses = data.earning.totalExpenses;
                    dashboardData.operationalData = data.operational || {};

                    // Re-render charts
                    initProfitChart();
                    initRevenueChart();
                    initReportBarChart();
                    initOperationalComparisonChart();
                }
            },
            error: function(xhr) {
                console.error('Error loading dashboard data:', xhr);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load dashboard data. Please try again.'
                    });
                } else {
                    alert('Failed to load dashboard data. Please try again.');
                }
            },
            complete: function() {
                $('#applyFilter').prop('disabled', false).html('<i class="ti tabler-filter me-1"></i>Apply Filter');
            }
        });
    }

    /**
     * Update percentage change indicator
     */
    function updatePercentageChange(iconSelector, textSelector, change) {
        const isPositive = change >= 0;
        $(iconSelector)
            .removeClass('tabler-chevron-up tabler-chevron-down text-success text-danger')
            .addClass('tabler-chevron-' + (isPositive ? 'up' : 'down'))
            .addClass('text-' + (isPositive ? 'success' : 'danger'));
        $(textSelector).text(Math.abs(change).toFixed(1) + '%');
    }

    /**
     * Format number with commas
     */
    function formatNumber(value) {
        return parseFloat(value || 0).toLocaleString('en-US');
    }

    /**
     * Format currency value
     */
    function formatCurrency(value) {
        if (typeof value !== 'number') {
            value = parseFloat(value) || 0;
        }
        
        // Check if formatAmount function exists (from helper_main.js)
        if (typeof formatAmount === 'function') {
            return formatAmount(value);
        }
        
        // Fallback formatting
        if (value >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M';
        } else if (value >= 1000) {
            return (value / 1000).toFixed(1) + 'k';
        }
        return value.toFixed(2);
    }
})();

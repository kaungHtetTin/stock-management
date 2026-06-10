/**
 * Stock Management — UI interactions
 */
(function ($) {
    'use strict';

    /* Subtle page entrance for cards without animate-in */
    $('.card:not(.animate-in), .form-card, .card-filter').each(function (i) {
        var $el = $(this);
        if (!$el.hasClass('animate-in')) {
            $el.css({
                animation: 'fadeSlideUp 0.45s cubic-bezier(0.4, 0, 0.2, 1) ' + (i * 0.04) + 's both'
            });
        }
    });

    /* Theme & brand persistence */
    const root = document.documentElement;
    const themeKey = 'app.theme';
    const brandKey = 'app.brand';
    const defaultBrand = '#087f74';

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        localStorage.setItem(themeKey, theme);
        $('.theme-segment-btn').removeClass('active');
        $('.theme-segment-btn[data-theme-set="' + theme + '"]').addClass('active');
    }

    function applyBrand(color) {
        root.style.setProperty('--color-primary', color);
        localStorage.setItem(brandKey, color);
        $('#brandColorInput').val(color);
        $('.theme-swatch').removeClass('active');
        $('.theme-swatch[data-brand="' + color + '"]').addClass('active');
    }

    const savedTheme = localStorage.getItem(themeKey) || 'light';
    const savedBrand = localStorage.getItem(brandKey) || defaultBrand;
    applyTheme(savedTheme);
    applyBrand(savedBrand);

    $('#themeToggleBtn').on('click', function (e) {
        e.stopPropagation();
        const $popover = $('#themePopover');
        const open = !$popover.prop('hidden');
        $popover.prop('hidden', open);
        $(this).attr('aria-expanded', !open);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#themeControl').length) {
            $('#themePopover').prop('hidden', true);
            $('#themeToggleBtn').attr('aria-expanded', 'false');
        }
    });

    $(document).on('click', '[data-theme-set]', function () {
        applyTheme($(this).data('theme-set'));
    });

    $(document).on('click', '.theme-swatch', function () {
        applyBrand($(this).data('brand'));
    });

    $('#brandColorInput').on('input change', function () {
        applyBrand(this.value);
    });

    /* Global nav search */
    const navPages = [
        { label: 'Dashboard', url: 'pages/dashboard.php' },
        { label: 'Items', url: 'pages/items/index.php' },
        { label: 'Categories', url: 'pages/categories/index.php' },
        { label: 'Customers', url: 'pages/customers/index.php' },
        { label: 'Balance', url: 'pages/balance/index.php' },
        { label: 'Stock In', url: 'pages/stock-in/index.php' },
        { label: 'Stock Out', url: 'pages/stock-out/index.php' },
        { label: 'Reports', url: 'pages/reports/index.php' },
        { label: 'Users', url: 'pages/users/index.php' }
    ];

    $('#globalSearch').on('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const q = $(this).val().toLowerCase().trim();
        if (!q) return;
        const match = navPages.find(function (p) { return p.label.toLowerCase().indexOf(q) > -1; });
        if (match) {
            window.location.href = (window.APP_BASE || '') + match.url;
        }
    });

    /* Sidebar toggle (mobile) */
    const $sidebar = $('#appSidebar');
    const $backdrop = $('#sidebarBackdrop');

    $('#sidebarToggle').on('click', function () {
        $sidebar.toggleClass('show');
        $backdrop.toggleClass('show');
    });

    $backdrop.on('click', function () {
        $sidebar.removeClass('show');
        $backdrop.removeClass('show');
    });

    $(window).on('resize', function () {
        if (window.innerWidth >= 992) {
            $sidebar.removeClass('show');
            $backdrop.removeClass('show');
        }
    });

    /* Delete confirmation modal */
    let deleteTargetUrl = '';

    $(document).on('click', '[data-delete]', function (e) {
        e.preventDefault();
        deleteTargetUrl = $(this).data('delete');
        $('#deleteItemName').text($(this).data('name') || 'this record');
        const extraMessage = $(this).data('delete-message') || 'This action cannot be undone.';
        $('#deleteExtraMessage').text(extraMessage);
        $('#confirmDeleteBtn').text($(this).data('confirm-label') || 'Delete');
        new bootstrap.Modal('#deleteModal').show();
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (deleteTargetUrl) {
            window.location.href = deleteTargetUrl;
        }
    });

    /* Approval modal */
    let approvalUrl = '';
    let rejectId = '';
    let rejectPostUrl = '';

    $(document).on('click', '[data-approve]', function (e) {
        e.preventDefault();
        approvalUrl = $(this).data('approve-url') || '';
        const name = $(this).data('name') || 'this request';
        $('#approvalModalTitle').text('Approve Request');
        $('#approvalModalBody').html('Approve stock request for <strong>' + name + '</strong>?');
        $('#rejectReasonGroup').addClass('d-none');
        $('#confirmRejectBtn').addClass('d-none');
        $('#confirmApproveBtn').removeClass('d-none');
        new bootstrap.Modal('#approvalModal').show();
    });

    $(document).on('click', '[data-reject]', function (e) {
        e.preventDefault();
        rejectId = String($(this).data('reject-id') || '');
        rejectPostUrl = $(this).data('reject-url') || window.location.pathname;
        const name = $(this).data('name') || 'this request';
        $('#approvalModalTitle').text('Reject Request');
        $('#approvalModalBody').html('Reject stock request for <strong>' + name + '</strong>?');
        $('#rejectReasonGroup').removeClass('d-none');
        $('#confirmApproveBtn').addClass('d-none');
        $('#confirmRejectBtn').removeClass('d-none');
        $('#rejectReason').val('');
        new bootstrap.Modal('#approvalModal').show();
    });

    $('#confirmApproveBtn').on('click', function () {
        if (!approvalUrl) return;
        window.location.href = approvalUrl;
    });

    $('#confirmRejectBtn').on('click', function () {
        const reason = $('#rejectReason').val().trim();
        if (!reason) {
            $('#rejectReason').addClass('is-invalid').focus();
            return;
        }
        $('#rejectReason').removeClass('is-invalid');
        if (!rejectId) return;

        $('#approvalRejectId').val(rejectId);
        $('#approvalRejectReason').val(reason);
        $('#approvalRejectForm').attr('action', rejectPostUrl).submit();
    });

    /* Client-side table search */
    $('#tableSearch').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('.data-table-searchable tbody tr').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) > -1);
        });
    });

    /* Item select auto-fill (forms / line rows) */
    $(document).on('change', '[data-item-select]', function () {
        const $opt = $(this).find('option:selected');
        const unit = $opt.data('unit') || '';
        const name = $opt.data('name') || '';
        const balance = $opt.data('balance');
        const $row = $(this).closest('[data-line-row]');
        const $scope = $row.length ? $row : $(this).closest('form');

        $scope.find('[data-item-name]').val(name);
        $scope.find('[data-item-unit]').val(unit);
        if (balance !== undefined) {
            $scope.find('[data-item-balance]').val(balance);
        }
    });

    /* Multi-line stock forms — clone rows, document-level handlers */
    function stockLinesScope($el) {
        return $el.closest('[data-stock-lines]');
    }

    function stockLinesContainer($scope) {
        return $scope.find('[data-line-container]').first();
    }

    function clearStockLineRow($row) {
        $row.find('input, select, textarea').each(function () {
            if (this.tagName === 'SELECT') {
                this.selectedIndex = 0;
            } else {
                $(this).val('');
            }
        });
    }

    function reindexStockLines($container) {
        const $rows = $container.find('[data-line-row]');
        const canRemove = $rows.length > 1;

        $rows.each(function (i) {
            const $row = $(this);
            $row.find('[name]').each(function () {
                const name = this.getAttribute('name');
                if (name) {
                    this.setAttribute('name', name.replace(/lines\[[^\]]+\]/, 'lines[' + i + ']'));
                }
            });
            $row.find('[data-line-number]').text(i + 1);
            $row.find('[data-remove-line]')
                .prop('disabled', !canRemove)
                .attr('aria-disabled', canRemove ? 'false' : 'true');
        });
    }

    $(document).on('click', '[data-add-line]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $scope = stockLinesScope($(this));
        const $container = stockLinesContainer($scope);
        const $first = $container.find('[data-line-row]').first();

        if (!$scope.length || !$first.length) {
            return;
        }

        const $clone = $first.clone(false);
        clearStockLineRow($clone);
        $container.append($clone);
        reindexStockLines($container);
    });

    $(document).on('click', '[data-remove-line]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (this.disabled) {
            return;
        }

        const $scope = stockLinesScope($(this));
        const $container = stockLinesContainer($scope);

        if ($container.find('[data-line-row]').length <= 1) {
            return;
        }

        $(this).closest('[data-line-row]').remove();
        reindexStockLines($container);
    });

    $(function () {
        $('[data-line-container]').each(function () {
            reindexStockLines($(this));
        });
    });

    /* Chart defaults */
    window.AppCharts = {
        colors: {
            primary: '#087f74',
            fruits: '#ea580c',
            gelato: '#db2777',
            icecream: '#2563eb',
            grid: 'rgb(15 23 42 / 8%)',
            text: '#69768a'
        },

        bar: function (canvasId, labels, values) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Balance',
                        data: values,
                        backgroundColor: [
                            AppCharts.colors.fruits,
                            AppCharts.colors.gelato,
                            AppCharts.colors.icecream
                        ],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: AppCharts.colors.text, font: { family: 'Inter' } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: AppCharts.colors.grid },
                            ticks: { color: AppCharts.colors.text, font: { family: 'Inter' } }
                        }
                    }
                }
            });
        },

        doughnut: function (canvasId, labels, values) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            AppCharts.colors.fruits,
                            AppCharts.colors.gelato,
                            AppCharts.colors.icecream
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 16, usePointStyle: true, font: { family: 'Inter', size: 12 } }
                        }
                    }
                }
            });
        },

        line: function (canvasId, labels, inData, outData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Stock In',
                            data: inData,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.08)',
                            fill: true,
                            tension: 0.35
                        },
                        {
                            label: 'Stock Out',
                            data: outData,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.05)',
                            fill: true,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, font: { family: 'Inter', size: 12 } }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { family: 'Inter' } } },
                        y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { font: { family: 'Inter' } } }
                    }
                }
            });
        }
    };

})(jQuery);

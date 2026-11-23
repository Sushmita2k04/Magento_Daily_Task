/**
 * Codilar_Task dashboard.
 *
 * Receives the page config (options, texts, first data set) from the server, renders the cards, charts and
 * tables, and reloads everything from the JSON endpoint when a filter changes. All task text is written with
 * textContent / DOM nodes only, never as HTML, so a task title cannot inject markup.
 */
define([], function () {
    'use strict';

    var PRESETS = [
        {key: 'all', text: 'allTime'},
        {key: '7', text: 'last7'},
        {key: '30', text: 'last30'},
        {key: 'month', text: 'thisMonth'}
    ];

    /**
     * @param {String} tag
     * @param {String} [cls]
     * @param {String} [text]
     * @returns {HTMLElement}
     */
    function el(tag, cls, text) {
        var node = document.createElement(tag);

        if (cls) {
            node.className = cls;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }

        return node;
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function fmtNumber(n) {
        return Number(n).toLocaleString();
    }

    function fmtPercent(n) {
        return String(Math.round(Number(n) * 10) / 10) + '%';
    }

    /** '#rrggbb' to a pale tint of the same colour, used behind pills and chips. */
    function tint(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16),
            g = parseInt(hex.slice(3, 5), 16),
            b = parseInt(hex.slice(5, 7), 16);

        return 'rgba(' + r + ',' + g + ',' + b + ',' + (alpha || 0.14) + ')';
    }

    /** Darken a '#rrggbb' colour for readable text on its own tint. */
    function ink(hex) {
        var f = 0.62;

        function c(i) {
            return Math.round(parseInt(hex.slice(i, i + 2), 16) * f);
        }

        return 'rgb(' + c(1) + ',' + c(3) + ',' + c(5) + ')';
    }

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /** Date maths on plain Y-m-d strings (UTC, so no timezone or DST surprises). */
    function parseYmd(s) {
        var p = String(s).split('-');

        return Date.UTC(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    }

    function toYmd(ms) {
        var d = new Date(ms);

        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
    }

    function addDays(ymd, days) {
        return toYmd(parseYmd(ymd) + days * 86400000);
    }

    function daysBetween(fromYmd, toYmd2) {
        return Math.round((parseYmd(toYmd2) - parseYmd(fromYmd)) / 86400000);
    }

    return function (config, root) {
        var texts = config.texts,
            options = config.options,
            $ = function (role, scope) {
                return (scope || root).querySelector('[data-role="' + role + '"]');
            },
            statusChips = {},
            priorityChips = {},
            charts = {},
            ChartLib = null,
            chartLoadFailed = false,
            state = {
                date_field: 'created',
                date_from: '',
                date_to: '',
                status: [],
                priority: []
            },
            requestId = 0,
            controller = null,
            timer = null,
            current = config.data;

        /* ------------------------------------------------------------------ filter state */

        function syncStateFrom(filters) {
            state.date_field = filters.date_field || 'created';
            state.date_from = filters.date_from || '';
            state.date_to = filters.date_to || '';
            state.status = (filters.status || []).slice();
            state.priority = (filters.priority || []).slice();
        }

        function toQuery() {
            var params = [];

            if (state.date_field !== 'created') {
                params.push('date_field=' + encodeURIComponent(state.date_field));
            }
            if (state.date_from) {
                params.push('date_from=' + encodeURIComponent(state.date_from));
            }
            if (state.date_to) {
                params.push('date_to=' + encodeURIComponent(state.date_to));
            }
            state.status.forEach(function (v) {
                params.push('status[]=' + encodeURIComponent(v));
            });
            state.priority.forEach(function (v) {
                params.push('priority[]=' + encodeURIComponent(v));
            });

            return params.join('&');
        }

        function toggle(list, value) {
            var i = list.indexOf(value);

            if (i === -1) {
                list.push(value);
            } else {
                list.splice(i, 1);
            }
        }

        function hasFilters() {
            return !!(state.date_from || state.date_to || state.status.length || state.priority.length);
        }

        function showError(message) {
            var box = $('error');

            if (message) {
                box.textContent = message;
                box.hidden = false;
            } else {
                box.hidden = true;
                box.textContent = '';
            }
        }

        /* ------------------------------------------------------------------ rendering */

        function renderCards(summary) {
            var total = summary.total,
                keys = ['total', 'completed', 'pending', 'in_progress', 'overdue'];

            keys.forEach(function (key) {
                var card = root.querySelector('[data-metric="' + key + '"]'),
                    value = summary[key],
                    sub = '';

                if (key === 'total') {
                    sub = state.date_from || state.date_to ? '' : texts.allTime;
                } else if (total > 0) {
                    sub = fmtPercent(value / total * 100) + ' ' + texts.ofTotal;
                }
                $('value', card).textContent = fmtNumber(value);
                $('sub', card).textContent = sub;
                card.classList.toggle('is-zero', value === 0);
            });
        }

        function legendItem(color, label, count, percent) {
            var li = el('li'),
                dot = el('span', 'cd-legend-dot');

            dot.style.background = color;
            li.appendChild(dot);
            li.appendChild(el('span', 'cd-legend-label', label));
            li.appendChild(el('span', 'cd-legend-count', fmtNumber(count)));
            li.appendChild(el('span', 'cd-legend-pct', '(' + fmtPercent(percent) + ')'));

            return li;
        }

        function renderCompletion(summary, distribution) {
            var bar = $('segbar'),
                legend = $('segbar-legend'),
                colors = options.status,
                summaryText;

            $('completion-percent').textContent = fmtPercent(summary.completion_percent);
            summaryText = fmtNumber(summary.completed) + ' / ' + fmtNumber(summary.total) + ' ' + texts.tasks;
            $('completion-text').textContent = summaryText;
            bar.setAttribute('aria-label', summaryText);
            clear(bar);
            clear(legend);

            distribution.forEach(function (item, i) {
                var seg;

                legend.appendChild(legendItem(colors[i].color, item.label, item.count, item.percent));
                if (item.count > 0) {
                    seg = el('div', 'cd-seg');
                    seg.style.width = item.percent + '%';
                    seg.style.background = colors[i].color;
                    seg.title = item.label + ': ' + fmtNumber(item.count);
                    bar.appendChild(seg);
                }
            });
        }

        function chartPanel(kind) {
            return root.querySelector('[data-chart="' + kind + '"]');
        }

        function renderChart(kind, type, distribution, palette, total) {
            var panel = chartPanel(kind),
                wrap = $('chart-wrap', panel),
                emptyMsg = $('chart-empty', panel),
                legend = $('legend', panel),
                center = $('chart-center', panel),
                labels = distribution.map(function (d) {
                    return d.label;
                }),
                values = distribution.map(function (d) {
                    return d.count;
                }),
                colors = palette.map(function (p) {
                    return p.color;
                }),
                chart;

            clear(legend);
            distribution.forEach(function (item, i) {
                legend.appendChild(legendItem(colors[i], item.label, item.count, item.percent));
            });

            wrap.hidden = total === 0;
            emptyMsg.hidden = total !== 0;
            emptyMsg.textContent = texts.noTasks;
            if (center) {
                clear(center);
                center.appendChild(el('strong', '', fmtNumber(total)));
                center.appendChild(el('span', '', texts.tasks));
            }
            if (total === 0 || !ChartLib) {
                if (charts[kind]) {
                    charts[kind].destroy();
                    charts[kind] = null;
                }
                if (chartLoadFailed && total > 0) {
                    emptyMsg.hidden = false;
                    emptyMsg.textContent = texts.chartError;
                    wrap.hidden = true;
                }

                return;
            }

            chart = charts[kind];
            if (chart) {
                chart.data.labels = labels;
                chart.data.datasets[0].data = values;
                chart.update();

                return;
            }

            charts[kind] = new ChartLib($('canvas', panel), {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: type === 'doughnut' ? 3 : 0,
                        borderColor: '#ffffff',
                        borderRadius: type === 'bar' ? 8 : 0,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: type === 'doughnut' ? '68%' : undefined,
                    animation: {duration: 300},
                    plugins: {
                        legend: {display: false},
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var count = ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed,
                                        sum = ctx.dataset.data.reduce(function (a, b) {
                                            return a + b;
                                        }, 0);

                                    return ctx.label + ': ' + fmtNumber(count) + ' (' +
                                        fmtPercent(sum ? count / sum * 100 : 0) + ')';
                                }
                            }
                        }
                    },
                    scales: type === 'bar' ? {
                        y: {beginAtZero: true, ticks: {precision: 0}, grid: {color: '#eef0f6'}},
                        x: {grid: {display: false}}
                    } : {}
                }
            });
        }

        function renderCharts(data) {
            var total = data.summary.total;

            renderChart('status', 'doughnut', data.status_distribution, options.status, total);
            renderChart('priority', 'bar', data.priority_distribution, options.priority, total);
        }

        function colorOf(list, key) {
            var found = list.filter(function (o) {
                return o.key === key;
            })[0];

            return found ? found.color : '#94a3b8';
        }

        function pill(label, color) {
            var node = el('span', 'cd-pill', label);

            node.style.setProperty('--pill-tint', tint(color, 0.16));
            node.style.setProperty('--pill-ink', ink(color));

            return node;
        }

        /** Only same-site http(s) links produced by the server are used as hrefs. */
        function safeHref(url) {
            return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
        }

        function titleNode(row, cls) {
            var href = safeHref(row.edit_url),
                node = href ? el('a', cls) : el('span', cls);

            node.textContent = row.title;
            node.title = row.title;
            if (href) {
                node.setAttribute('href', href);
            }

            return node;
        }

        function renderRecent(rows) {
            var body = $('recent-body'),
                empty = $('recent-empty'),
                wrap = $('recent-wrap');

            clear(body);
            wrap.hidden = rows.length === 0;
            empty.hidden = rows.length !== 0;
            empty.textContent = hasFilters() ? texts.noTasks : texts.noRecent;

            rows.forEach(function (row) {
                var tr = el('tr'),
                    task = el('td'),
                    due = el('td'),
                    dueText;

                task.appendChild(titleNode(row, 'cd-task-title'));
                task.appendChild(el('span', 'cd-task-meta', texts.created + ' ' + row.created_at_formatted));
                tr.appendChild(task);

                tr.appendChild(el('td')).appendChild(pill(row.status_label, colorOf(options.status, row.status)));
                tr.appendChild(el('td')).appendChild(pill(row.priority_label, colorOf(options.priority, row.priority)));

                dueText = row.due_date ? row.due_date_formatted : '—';
                due.className = 'cd-due' + (row.is_overdue ? ' cd-due-overdue' : '');
                due.textContent = dueText;
                tr.appendChild(due);
                body.appendChild(tr);
            });
        }

        function renderOverdue(rows, total, today) {
            var list = $('overdue-list'),
                empty = $('overdue-empty'),
                badge = $('overdue-count');

            clear(list);
            list.hidden = rows.length === 0;
            empty.hidden = rows.length !== 0;
            empty.textContent = texts.noOverdue;
            badge.hidden = total === 0;
            badge.textContent = fmtNumber(total) + ' ' + texts.overdue.toLowerCase();

            rows.forEach(function (row) {
                var li = el('li', 'cd-overdue-item'),
                    main = el('div', 'cd-overdue-main'),
                    side = el('div', 'cd-overdue-side'),
                    days = daysBetween(row.due_date, today);

                main.appendChild(titleNode(row, 'cd-task-title'));
                main.appendChild(el('span', 'cd-task-meta', texts.due + ' ' + row.due_date_formatted));
                side.appendChild(el('span', 'cd-overdue-days', fmtNumber(days) + ' ' + (days === 1 ? texts.dayLate : texts.daysLate)));
                side.appendChild(pill(row.priority_label, colorOf(options.priority, row.priority)));
                li.appendChild(main);
                li.appendChild(side);
                list.appendChild(li);
            });
        }

        function render(data) {
            current = data;
            renderCards(data.summary);
            renderCompletion(data.summary, data.status_distribution);
            renderCharts(data);
            renderRecent(data.recent_tasks);
            renderOverdue(data.overdue_tasks, data.summary.overdue, data.today);
            syncControls();
        }

        /* ------------------------------------------------------------------ filter controls */

        function buildChips(kind, containerRole, optionList, registry) {
            var container = $(containerRole);

            optionList.forEach(function (opt) {
                var button = el('button', 'cd-chip'),
                    dot = el('span', 'cd-chip-dot');

                button.type = 'button';
                button.style.setProperty('--chip-color', opt.color);
                button.style.setProperty('--chip-tint', tint(opt.color, 0.12));
                dot.style.background = opt.color;
                button.appendChild(dot);
                button.appendChild(el('span', '', opt.label));
                button.addEventListener('click', function () {
                    toggle(state[kind], opt.key);
                    syncControls();
                    schedule();
                });
                registry[opt.key] = button;
                container.appendChild(button);
            });
        }

        function presetRange(key) {
            var today = current.today;

            if (key === 'all') {
                return ['', ''];
            }
            if (key === 'month') {
                return [today.slice(0, 8) + '01', today];
            }

            return [addDays(today, -(Number(key) - 1)), today];
        }

        function buildPresets() {
            var container = $('presets');

            PRESETS.forEach(function (preset) {
                var button = el('button', 'cd-preset', texts[preset.text]),
                    range;

                button.type = 'button';
                button.setAttribute('data-preset', preset.key);
                button.addEventListener('click', function () {
                    range = presetRange(preset.key);
                    state.date_from = range[0];
                    state.date_to = range[1];
                    syncControls();
                    schedule();
                });
                container.appendChild(button);
            });
        }

        function syncControls() {
            $('date-field').value = state.date_field;
            $('date-from').value = state.date_from;
            $('date-to').value = state.date_to;

            Object.keys(statusChips).forEach(function (key) {
                statusChips[key].setAttribute('aria-pressed', state.status.indexOf(Number(key)) !== -1 ? 'true' : 'false');
            });
            Object.keys(priorityChips).forEach(function (key) {
                priorityChips[key].setAttribute('aria-pressed', state.priority.indexOf(Number(key)) !== -1 ? 'true' : 'false');
            });

            Array.prototype.forEach.call($('presets').children, function (button) {
                var range = presetRange(button.getAttribute('data-preset'));

                button.setAttribute('aria-pressed', range[0] === state.date_from && range[1] === state.date_to ? 'true' : 'false');
            });
        }

        function buildControls() {
            var select = $('date-field');

            options.dateField.forEach(function (opt) {
                var option = el('option', '', opt.label);

                option.value = opt.key;
                select.appendChild(option);
            });

            buildChips('status', 'status-chips', options.status, statusChips);
            buildChips('priority', 'priority-chips', options.priority, priorityChips);
            buildPresets();

            select.addEventListener('change', function () {
                state.date_field = select.value;
                schedule();
            });
            $('date-from').addEventListener('change', function () {
                state.date_from = this.value;
                syncControls();
                schedule();
            });
            $('date-to').addEventListener('change', function () {
                state.date_to = this.value;
                syncControls();
                schedule();
            });
            $('reset').addEventListener('click', function () {
                syncStateFrom({});
                syncControls();
                schedule();
            });
            $('filters').addEventListener('submit', function (event) {
                event.preventDefault();
            });
        }

        /* ------------------------------------------------------------------ loading */

        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(load, 250);
        }

        function updateUrl(query) {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
            }
        }

        function load() {
            var query = toQuery(),
                id = ++requestId,
                separator = config.dataUrl.indexOf('?') === -1 ? '?' : '&';

            if (state.date_from && state.date_to && state.date_from > state.date_to) {
                showError(texts.badRange);

                return;
            }
            showError('');

            if (controller && controller.abort) {
                controller.abort();
            }
            controller = window.AbortController ? new window.AbortController() : null;
            root.setAttribute('aria-busy', 'true');

            window.fetch(config.dataUrl + (query ? separator + query : ''), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                return response.json().then(function (body) {
                    return {status: response.status, body: body};
                }, function () {
                    return {status: response.status, body: null};
                });
            }).then(function (result) {
                if (id !== requestId) {
                    return; // a newer request superseded this one
                }
                root.setAttribute('aria-busy', 'false');
                if (result.body && result.body.success) {
                    render(result.body.data);
                    updateUrl(query);
                } else {
                    showError(result.body && result.body.errors && result.body.errors.length ?
                        result.body.errors.join(' ') : texts.loadError);
                }
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (id === requestId) {
                    root.setAttribute('aria-busy', 'false');
                    showError(texts.loadError);
                }
            });
        }

        /* ------------------------------------------------------------------ start */

        function start() {
            var manage = $('manage-link'),
                viewAll = $('view-all');

            if (config.gridUrl) {
                [manage, viewAll].forEach(function (link) {
                    link.setAttribute('href', config.gridUrl);
                    link.hidden = false;
                });
            }
            syncStateFrom(config.data.filters);
            buildControls();
            if (config.initialError) {
                showError(config.initialError);
            }
            render(config.data);
        }

        start();

        // Chart.js ships with Magento (alias "chartJs"). Load it after the first paint so the page never waits for it.
        window.require(['chartJs'], function (Chart) {
            ChartLib = Chart;
            renderCharts(current);
        }, function () {
            chartLoadFailed = true;
            renderCharts(current);
        });
    };
});

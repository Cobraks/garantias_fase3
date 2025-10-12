(function () {
    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function parseOr(value, fallback) {
        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function setupResizableTable(table) {
        if (!table || table.dataset.resizableInitialized === 'true') {
            return;
        }
        if (!table.tHead || window.innerWidth < 1024) {
            return;
        }

        var wrapper = table.parentElement;
        if (!wrapper) {
            return;
        }

        var colgroup = table.querySelector('colgroup');
        if (!colgroup) {
            return;
        }

        var cols = Array.prototype.slice.call(colgroup.children);
        var headers = Array.prototype.slice.call(table.tHead.rows[0].cells || []);
        if (!cols.length || cols.length !== headers.length) {
            return;
        }

        wrapper.style.position = wrapper.style.position || 'relative';
        table.style.tableLayout = 'fixed';

        var fallbackMin = 140;
        var fallbackMax = 360;
        var columns = cols.map(function (col, index) {
            var min = parseOr(col.getAttribute('data-min-width'), fallbackMin);
            var max = parseOr(col.getAttribute('data-max-width'), fallbackMax);
            if (min > max) {
                min = fallbackMin;
                max = fallbackMax;
            }
            var def = parseOr(col.getAttribute('data-default-width'), 0);
            var headerWidth = headers[index] ? headers[index].getBoundingClientRect().width : def;
            var base = def || headerWidth || min;
            var width = clamp(base, min, max);
            col.style.width = width + 'px';
            return { min: min, max: max, width: width };
        });

        var overlay = document.createElement('div');
        overlay.className = 'column-resizers column-resizers--averias';
        wrapper.appendChild(overlay);

        var handles = [];

        function updateOverlay() {
            if (!wrapper.contains(overlay)) {
                return;
            }
            overlay.style.width = table.offsetWidth + 'px';
            overlay.style.height = table.offsetHeight + 'px';
            overlay.style.top = table.offsetTop + 'px';
            overlay.style.left = table.offsetLeft + 'px';
            handles.forEach(function (handle, index) {
                var header = headers[index];
                if (!header) {
                    return;
                }
                var left = header.offsetLeft + header.offsetWidth;
                handle.style.left = left - 4 + 'px';
                handle.style.height = table.offsetHeight + 'px';
            });
        }

        function bindHandle(handle, index) {
            handle.addEventListener('mousedown', function (event) {
                event.preventDefault();
                var startX = event.pageX;
                var current = columns[index];
                var next = columns[index + 1];
                if (!current || !next) {
                    return;
                }
                var startWidth = current.width;
                var startNextWidth = next.width;
                var total = startWidth + startNextWidth;

                function onMove(ev) {
                    var delta = ev.pageX - startX;
                    var newWidth = clamp(startWidth + delta, current.min, current.max);
                    var newNextWidth = total - newWidth;
                    if (newNextWidth < next.min) {
                        newNextWidth = next.min;
                        newWidth = total - newNextWidth;
                    }
                    if (newNextWidth > next.max) {
                        newNextWidth = next.max;
                        newWidth = total - newNextWidth;
                    }
                    newWidth = clamp(newWidth, current.min, current.max);
                    newNextWidth = clamp(newNextWidth, next.min, next.max);
                    columns[index].width = newWidth;
                    columns[index + 1].width = newNextWidth;
                    cols[index].style.width = newWidth + 'px';
                    cols[index + 1].style.width = newNextWidth + 'px';
                    updateOverlay();
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        }

        for (var i = 0; i < columns.length - 1; i += 1) {
            var handle = document.createElement('span');
            handle.className = 'column-resizer column-resizer--averias';
            overlay.appendChild(handle);
            handles.push(handle);
            bindHandle(handle, i);
        }

        var body = table.tBodies[0];
        if (body) {
            var observer = new MutationObserver(function () {
                requestAnimationFrame(updateOverlay);
            });
            observer.observe(body, { childList: true, subtree: false });
        }

        window.addEventListener('resize', function () {
            requestAnimationFrame(updateOverlay);
        });
        wrapper.addEventListener('scroll', function () {
            requestAnimationFrame(updateOverlay);
        }, { passive: true });

        requestAnimationFrame(updateOverlay);
        table.dataset.resizableInitialized = 'true';
        table.__averiasUpdateOverlay = updateOverlay;
    }

    onReady(function () {
        var tables = Array.prototype.slice.call(document.querySelectorAll('.guarantees-table--averias'));

        function initTables() {
            if (window.innerWidth < 1024) {
                return;
            }
            tables.forEach(function (table) {
                setupResizableTable(table);
            });
        }

        function refreshTableOverlays() {
            tables.forEach(function (table) {
                if (typeof table.__averiasUpdateOverlay === 'function') {
                    table.__averiasUpdateOverlay();
                }
            });
        }

        initTables();
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                requestAnimationFrame(initTables);
            }
        });

        var stickyFilters = Array.prototype.slice.call(document.querySelectorAll('[data-sticky-target]'));
        var header = document.querySelector('.top-bar');

        if (stickyFilters.length && header) {
            var stickyConfigs = stickyFilters.map(function (filter) {
                var targetSelector = filter.getAttribute('data-sticky-target');
                var target = targetSelector ? document.querySelector(targetSelector) : null;
                return { filter: filter, target: target };
            });

            var observer = new IntersectionObserver(function (entries) {
                var entry = entries && entries.length ? entries[0] : null;
                var shouldStick = entry ? !entry.isIntersecting : false;

                stickyConfigs.forEach(function (config) {
                    config.filter.classList.toggle('sticky-active', shouldStick);
                    if (config.target) {
                        config.target.classList.toggle('sticky-active', shouldStick);
                    }
                });

                requestAnimationFrame(refreshTableOverlays);
            }, { root: null, threshold: 0, rootMargin: '-50px' });

            observer.observe(header);
        }

        var listSection = document.querySelector('.guarantees-list--averias');
        if (listSection) {
            var updateBodyScrolled = function () {
                var scrolled = listSection.scrollTop > 10;
                document.body.classList.toggle('scrolled', scrolled);
            };

            listSection.addEventListener('scroll', function () {
                requestAnimationFrame(updateBodyScrolled);
            }, { passive: true });

            requestAnimationFrame(updateBodyScrolled);
        }

        var navigableRows = Array.prototype.slice.call(document.querySelectorAll('.guarantees-table__row[data-expediente-url]'));

        navigableRows.forEach(function (row) {
            var href = row.getAttribute('data-expediente-url');
            if (!href) {
                return;
            }

            row.addEventListener('click', function (event) {
                var interactive = event.target && event.target.closest('a, button, input, label');
                if (interactive) {
                    return;
                }
                window.location.href = href;
            });

            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = href;
                }
            });
        });
    });
})();

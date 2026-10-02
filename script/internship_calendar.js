(function (global) {
    "use strict";

    function parseDate(value) {
        var m;
        value = String(value || "").trim();
        m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (m)
            return validDate(+m[1], +m[2], +m[3]);
        m = value.match(/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/);
        if (m)
            return validDate(+m[3], +m[2], +m[1]);
        return "";
    }

    function validDate(year, month, day) {
        var d = new Date(Date.UTC(year, month - 1, day));
        if (d.getUTCFullYear() !== year || d.getUTCMonth() !== month - 1 || d.getUTCDate() !== day)
            return "";
        return iso(d);
    }

    function iso(date) {
        return date.getUTCFullYear() + "-" + String(date.getUTCMonth() + 1).padStart(2, "0") + "-" + String(date.getUTCDate()).padStart(2, "0");
    }

    function fromIso(value) {
        var m = String(value || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])) : null;
    }

    function addDays(value, days) {
        var d = fromIso(value);
        if (!d)
            return "";
        d.setUTCDate(d.getUTCDate() + days);
        return iso(d);
    }

    function easterSunday(year) {
        var a = year % 19;
        var b = Math.floor(year / 100);
        var c = year % 100;
        var d = Math.floor(b / 4);
        var e = b % 4;
        var f = Math.floor((b + 8) / 25);
        var g = Math.floor((b - f + 1) / 3);
        var h = (19 * a + b - d - g + 15) % 30;
        var i = Math.floor(c / 4);
        var k = c % 4;
        var l = (32 + 2 * e + 2 * i - h - k) % 7;
        var m = Math.floor((a + 11 * h + 22 * l) / 451);
        var month = Math.floor((h + l - 7 * m + 114) / 31);
        var day = ((h + l - 7 * m + 114) % 31) + 1;
        return validDate(year, month, day);
    }

    function holidays(year) {
        var easter = easterSunday(year);
        var out = {};
        out[year + "-01-01"] = "Jour de l’An";
        out[year + "-05-01"] = "Fête du Travail";
        out[year + "-05-08"] = "Victoire 1945";
        out[year + "-07-14"] = "Fête nationale";
        out[year + "-08-15"] = "Assomption";
        out[year + "-11-01"] = "Toussaint";
        out[year + "-11-11"] = "Armistice 1918";
        out[year + "-12-25"] = "Noël";
        out[addDays(easter, 1)] = "Lundi de Pâques";
        out[addDays(easter, 39)] = "Ascension";
        out[addDays(easter, 50)] = "Lundi de Pentecôte";
        return out;
    }

    function holidayName(value) {
        var d = fromIso(value);
        if (!d)
            return "";
        return holidays(d.getUTCFullYear())[value] || "";
    }

    function decode(raw) {
        var data;
        try { data = JSON.parse(String(raw || "")); } catch (ignore) { data = {}; }
        if (!data || typeof data !== "object")
            data = {};
        if (!data.days || typeof data.days !== "object" || Array.isArray(data.days))
            data.days = {};
        return {start: parseDate(data.start) || "", end: parseDate(data.end) || "", days: data.days};
    }

    function field(root, name, attrName) {
        var list = root.querySelectorAll("[" + attrName + "]");
        var i;
        for (i = 0; i < list.length; ++i)
            if (list[i].getAttribute(attrName) === name)
                return list[i];
        return null;
    }

    function normalizeStatus(value) {
        value = String(value || "").toUpperCase();
        return value === "T" || value === "E" || value === "F" ? value : "";
    }

    function normalizeRange(state, start, end) {
        var startDate = fromIso(start);
        var endDate = fromIso(end);
        var out = {};
        var cursor;
        var date;
        var incoming;
        var holiday;
        var am;
        var pm;
        if (!startDate || !endDate || startDate > endDate)
            return {start: start || "", end: end || "", days: {}};
        if ((endDate - startDate) / 86400000 > 1461)
            return {start: start, end: end, days: {}};
        cursor = new Date(startDate.getTime());
        while (cursor <= endDate) {
            date = iso(cursor);
            incoming = state.days[date] || {};
            holiday = !!holidayName(date);
            am = normalizeStatus(incoming.am || incoming.Morning);
            pm = normalizeStatus(incoming.pm || incoming.Afternoon);
            if (am !== "T" && am !== "E") am = holiday ? "F" : "";
            if (pm !== "T" && pm !== "E") pm = holiday ? "F" : "";
            if (am || pm)
                out[date] = {am: am, pm: pm};
            cursor.setUTCDate(cursor.getUTCDate() + 1);
        }
        return {start: start, end: end, days: out};
    }

    function monthStart(date) {
        return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
    }

    function monthAfter(date) {
        return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 1));
    }

    function monthLabel(date) {
        var names = ["Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
        return names[date.getUTCMonth()] + " " + date.getUTCFullYear();
    }

    function isoWeekKey(value) {
        var d = fromIso(value);
        var day;
        var year;
        var first;
        var week;
        if (!d)
            return value.slice(0, 7);
        day = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - day);
        year = d.getUTCFullYear();
        first = new Date(Date.UTC(year, 0, 1));
        week = Math.ceil((((d - first) / 86400000) + 1) / 7);
        return year + "-" + String(week).padStart(2, "0");
    }

    function modeHigh(values) {
        var counts = {};
        var best = 0;
        var bestCount = -1;
        values.forEach(function (value) {
            var key = Number(value).toFixed(6);
            counts[key] = (counts[key] || 0) + 1;
            if (counts[key] > bestCount || (counts[key] === bestCount && Number(value) > best)) {
                best = Number(value);
                bestCount = counts[key];
            }
        });
        return best;
    }

    function workMetrics(raw, morningHours, afternoonHours) {
        var state = typeof raw === "string" ? decode(raw) : raw;
        var daily = {};
        var dailyDays = {};
        var weekly = {};
        var monthly = {};
        var total = 0;
        morningHours = Math.max(0, Number(morningHours));
        afternoonHours = Math.max(0, Number(afternoonHours));
        if (!isFinite(morningHours)) morningHours = 3.5;
        if (!isFinite(afternoonHours)) afternoonHours = 3.5;
        if (!state || !state.days)
            state = {days: {}};
        Object.keys(state.days).sort().forEach(function (date) {
            var entry = state.days[date] || {};
            var hours = 0;
            var halfDays = 0;
            if (normalizeStatus(entry.am || entry.Morning) === "T") {
                hours += morningHours;
                halfDays++;
            }
            if (normalizeStatus(entry.pm || entry.Afternoon) === "T") {
                hours += afternoonHours;
                halfDays++;
            }
            if (halfDays <= 0)
                return;
            var dayEquivalent = halfDays / 2;
            daily[date] = hours;
            dailyDays[date] = dayEquivalent;
            total += hours;
            weekly[isoWeekKey(date)] = (weekly[isoWeekKey(date)] || 0) + dayEquivalent;
            monthly[date.slice(0, 7)] = (monthly[date.slice(0, 7)] || 0) + hours;
        });
        return {
            days: Object.keys(dailyDays).reduce(function (sum, date) { return sum + dailyDays[date]; }, 0),
            hours: total,
            hoursPerDay: modeHigh(Object.keys(daily).map(function (date) { return daily[date]; })),
            daysPerWeek: modeHigh(Object.keys(weekly).map(function (week) { return weekly[week]; })),
            monthlyHours: monthly,
            dailyHours: daily,
            dailyDays: dailyDays
        };
    }

    function stats(state) {
        var count = {T: 0, E: 0, F: 0};
        Object.keys(state.days).forEach(function (date) {
            ["am", "pm"].forEach(function (half) {
                var value = normalizeStatus((state.days[date] || {})[half]);
                if (Object.prototype.hasOwnProperty.call(count, value))
                    ++count[value];
            });
        });
        return count;
    }

    function attachEditor(root, editor, attrName, notify) {
        var hidden = editor.querySelector("input[type=hidden][" + attrName + "]");
        var startInput = field(root, editor.getAttribute("data-start-field") || "", attrName);
        var endInput = field(root, editor.getAttribute("data-end-field") || "", attrName);
        var months = editor.querySelector("[data-internship-calendar-months]");
        var statsBox = editor.querySelector("[data-internship-calendar-stats]");
        var state = decode(hidden ? hidden.value : "");
        var morningHours = parseFloat(editor.getAttribute("data-morning-hours") || "3.5");
        var afternoonHours = parseFloat(editor.getAttribute("data-afternoon-hours") || "3.5");
        var tool = "T";

        if (!hidden || !months)
            return;

        function currentRange() {
            return {
                start: parseDate(startInput ? startInput.value : state.start),
                end: parseDate(endInput ? endInput.value : state.end)
            };
        }

        function persist(shouldNotify) {
            var range = currentRange();
            var startDate = fromIso(range.start);
            var endDate = fromIso(range.end);
            // Do not destroy an already painted calendar while somebody is in
            // the middle of typing a date.  Clipping/default F insertion only
            // happens once both bounds form a valid range.
            if (startDate && endDate && startDate <= endDate &&
                (endDate - startDate) / 86400000 <= 1461)
                state = normalizeRange(state, range.start, range.end);
            else {
                state.start = range.start || state.start || "";
                state.end = range.end || state.end || "";
            }
            hidden.value = JSON.stringify(state);
            if (shouldNotify && typeof notify === "function")
                notify(hidden);
        }

        function status(date, half) {
            var entry = state.days[date] || {};
            return normalizeStatus(entry[half]) || (holidayName(date) ? "F" : "");
        }

        function setStatus(date, half) {
            var value = tool === "erase" ? (holidayName(date) ? "F" : "") : tool;
            if (!state.days[date])
                state.days[date] = {am: "", pm: ""};
            state.days[date][half] = value;
            if (!state.days[date].am && !state.days[date].pm)
                delete state.days[date];
            persist(true);
            render();
        }

        function halfButton(date, half, label) {
            var button = document.createElement("button");
            var value = status(date, half);
            button.type = "button";
            button.className = "internship-calendar-half status-" + (value || "empty").toLowerCase();
            button.setAttribute("data-half", half);
            button.title = label + " — " + (value || "vide");
            button.innerHTML = '<span>' + label + '</span><b>' + (value || "·") + '</b>';
            button.addEventListener("click", function () { setStatus(date, half); });
            return button;
        }

        function renderMonth(first, start, end) {
            var box = document.createElement("div");
            var title = document.createElement("h4");
            var grid = document.createElement("div");
            var weekdays = ["L", "M", "M", "J", "V", "S", "D"];
            var firstWeekday = (first.getUTCDay() + 6) % 7;
            var cursor = new Date(first.getTime());
            var last = monthAfter(first);
            var i;
            var empty;
            var date;
            var day;
            var holiday;

            box.className = "internship-calendar-month";
            title.textContent = monthLabel(first);
            grid.className = "internship-calendar-grid";
            box.appendChild(title);
            weekdays.forEach(function (name) {
                var head = document.createElement("div");
                head.className = "internship-calendar-weekday";
                head.textContent = name;
                grid.appendChild(head);
            });
            for (i = 0; i < firstWeekday; ++i) {
                empty = document.createElement("div");
                empty.className = "internship-calendar-day is-outside";
                grid.appendChild(empty);
            }
            while (cursor < last) {
                date = iso(cursor);
                day = document.createElement("div");
                holiday = holidayName(date);
                day.className = "internship-calendar-day";
                if (cursor.getUTCDay() === 0 || cursor.getUTCDay() === 6)
                    day.classList.add("is-weekend");
                if (holiday)
                    day.classList.add("is-holiday");
                if (date < start || date > end) {
                    day.classList.add("is-outside");
                    day.innerHTML = '<div class="internship-calendar-number">' + cursor.getUTCDate() + '</div>';
                } else {
                    day.innerHTML = '<div class="internship-calendar-number"' + (holiday ? ' title="' + holiday.replace(/"/g, "&quot;") + '"' : '') + '>' + cursor.getUTCDate() + '</div>';
                    day.appendChild(halfButton(date, "am", "M"));
                    day.appendChild(halfButton(date, "pm", "A"));
                }
                grid.appendChild(day);
                cursor.setUTCDate(cursor.getUTCDate() + 1);
            }
            box.appendChild(grid);
            return box;
        }

        function render() {
            var range = currentRange();
            var start = fromIso(range.start);
            var end = fromIso(range.end);
            var cursor;
            var count;
            persist(false);
            months.innerHTML = "";
            if (!start || !end) {
                months.innerHTML = '<p class="internship-calendar-empty">Renseignez les dates de début et de fin pour afficher le calendrier.</p>';
            } else if (start > end) {
                months.innerHTML = '<p class="internship-calendar-empty is-error">La date de fin précède la date de début.</p>';
            } else if ((end - start) / 86400000 > 1461) {
                months.innerHTML = '<p class="internship-calendar-empty is-error">La période ne peut pas dépasser quatre ans.</p>';
            } else {
                cursor = monthStart(start);
                while (cursor <= end) {
                    months.appendChild(renderMonth(cursor, range.start, range.end));
                    cursor = monthAfter(cursor);
                }
            }
            count = stats(state);
            if (statsBox) {
                var metrics = workMetrics(state, morningHours, afternoonHours);
                statsBox.textContent = "T : " + count.T + " demi-journée(s) · E : " + count.E + " · F : " + count.F +
                    " · Présence en entreprise : " + metrics.days.toLocaleString("fr-FR", {maximumFractionDigits: 2}) + " jour(s), " +
                    metrics.hours.toLocaleString("fr-FR", {maximumFractionDigits: 2}) + " h";
            }
            hidden.value = JSON.stringify(state);
        }

        editor.querySelectorAll("[data-internship-calendar-tool]").forEach(function (button) {
            button.addEventListener("click", function () {
                tool = button.getAttribute("data-internship-calendar-tool") || "T";
                editor.querySelectorAll("[data-internship-calendar-tool]").forEach(function (other) {
                    other.classList.toggle("is-active", other === button);
                });
            });
        });
        [startInput, endInput].forEach(function (input) {
            if (!input)
                return;
            input.addEventListener("input", render);
            input.addEventListener("change", function () {
                persist(true);
                render();
            });
        });
        render();
    }

    function attach(root, attrName, notify) {
        if (!root)
            return;
        root.querySelectorAll("[data-internship-calendar]").forEach(function (editor) {
            attachEditor(root, editor, attrName, notify);
        });
    }

    global.InfosphereInternshipCalendar = {attach: attach, decode: decode, workMetrics: workMetrics};
}(window));

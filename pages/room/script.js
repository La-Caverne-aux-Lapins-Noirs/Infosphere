(function () {
    "use strict";

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function qs(parent, selector) {
        return parent ? parent.querySelector(selector) : null;
    }

    function qsa(parent, selector) {
        return Array.prototype.slice.call((parent || document).querySelectorAll(selector));
    }

    function roomDesignLeft(mapRect) {
        return mapRect.right - 1250;
    }

    function deskXFromPointer(clientX, mapRect, offsetX) {
        return clamp(Math.round(clientX - roomDesignLeft(mapRect) - offsetX + 40), 0, 1250);
    }

    function setDeskPosition(desk, x, y) {
        x = clamp(Math.round(x), 0, 1250);
        y = clamp(Math.round(y), 0, 460);
        desk.dataset.x = String(x);
        desk.dataset.y = String(y);
        desk.style.right = String(1250 - x) + "px";
        desk.style.top = String(y) + "px";
    }

    function postDesk(url, method, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            if (data[key] !== undefined && data[key] !== null)
                body.append(key, data[key]);
        });
        return fetch(url, {
            method: method,
            credentials: "same-origin",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: body.toString()
        });
    }

    function sameRoomSelector(roomId, deskId, selector) {
        return selector + "[data-room-id='" + roomId + "'][data-desk-id='" + deskId + "']";
    }

    function highlight(roomId, deskId, on) {
        qsa(document, sameRoomSelector(roomId, deskId, ".desk")).forEach(function (el) {
            el.classList.toggle("room_linked", on);
        });
        qsa(document, sameRoomSelector(roomId, deskId, ".room_presence_avatar")).forEach(function (el) {
            el.classList.toggle("room_linked", on);
        });
    }

    function ensureTooltip() {
        var tooltip = document.getElementById("room_desk_tooltip");
        if (!tooltip) {
            tooltip = document.createElement("div");
            tooltip.id = "room_desk_tooltip";
            tooltip.className = "room_desk_tooltip";
            document.body.appendChild(tooltip);
        }
        return tooltip;
    }

    function moveTooltip(ev) {
        var tooltip = ensureTooltip();
        var margin = 16;
        var x = ev.clientX + margin;
        var y = ev.clientY + margin;
        tooltip.style.display = "block";
        var rect = tooltip.getBoundingClientRect();
        if (x + rect.width + margin > window.innerWidth)
            x = ev.clientX - rect.width - margin;
        if (y + rect.height + margin > window.innerHeight)
            y = ev.clientY - rect.height - margin;
        tooltip.style.left = String(Math.max(margin, x)) + "px";
        tooltip.style.top = String(Math.max(margin, y)) + "px";
    }

    function roomParts(room) {
        var roomId = room.dataset.roomId;
        return {
            roomId: roomId,
            map: qs(room, "[data-room-map='" + roomId + "']"),
            editToggle: qs(room, "[data-room-edit-toggle='" + roomId + "']"),
            newButton: qs(room, "[data-room-new-desk='" + roomId + "']"),
            changeMapButton: qs(room, "[data-room-change-map='" + roomId + "']"),
            mapInput: qs(room, ".room_map_editor input[type='file']"),
            picker: qs(room, "[data-room-desk-picker='" + roomId + "']"),
            saveButton: qs(room, "[data-room-save-desk='" + roomId + "']"),
            cancelButton: qs(room, "[data-room-cancel-desk='" + roomId + "']"),
            form: qs(room, "[data-room-desk-editor='" + roomId + "']"),
            hint: qs(room, "[data-room-editor-hint='" + roomId + "']")
        };
    }

    function setEditMode(room, enabled) {
        var parts = roomParts(room);
        room.classList.toggle("room_edit_mode", enabled);
        if (parts.editToggle)
            parts.editToggle.textContent = enabled ? "Fermer l’éditeur" : "Éditer les postes";
        if (parts.hint)
            parts.hint.textContent = enabled ? "Déplace un poste, choisis un poste existant, ou utilise « Nouveau poste »." : "Choisis un poste existant pour le modifier, ou crée un nouveau poste puis clique sur le plan.";
        if (!enabled) {
            room.dataset.roomNewMode = "0";
            qsa(room, ".desk.room_editor_selected").forEach(function (el) {
                el.classList.remove("room_editor_selected");
            });
        }
    }

    function fillEditor(room, desk, x, y) {
        var parts = roomParts(room);
        var form = parts.form;
        if (!form)
            return;
        setEditMode(room, true);
        form.classList.add("room_editor_open");
        form.dataset.deskId = desk ? desk.dataset.deskId : "";
        if (parts.picker)
            parts.picker.value = desk ? desk.dataset.deskId : "";
        form.elements.id_room.value = parts.roomId;
        form.elements.x.value = desk ? desk.dataset.x : String(x || 80);
        form.elements.y.value = desk ? desk.dataset.y : String(y || 40);
        form.elements.codename.value = desk ? desk.dataset.codename : "";
        form.elements.mac.value = desk ? desk.dataset.mac : "";
        form.elements.ip.value = desk ? desk.dataset.ip : "";
        form.elements.type.value = desk ? desk.dataset.type : "0";
        form.elements.misc.value = desk ? desk.dataset.misc : "";
        if (parts.saveButton)
            parts.saveButton.disabled = false;
        qsa(room, ".desk.room_editor_selected").forEach(function (el) {
            el.classList.remove("room_editor_selected");
        });
        if (desk)
            desk.classList.add("room_editor_selected");
        if (parts.hint)
            parts.hint.textContent = desk ? "Poste sélectionné : modifie les champs ou déplace-le sur le plan." : "Nouveau poste : complète les informations puis enregistre.";
    }

    function collectEditorData(form) {
        return {
            id_room: form.elements.id_room.value,
            x: form.elements.x.value,
            y: form.elements.y.value,
            codename: form.elements.codename.value,
            mac: form.elements.mac.value,
            ip: form.elements.ip.value,
            type: form.elements.type.value,
            misc: form.elements.misc.value
        };
    }

    function findRoomFromElement(el) {
        return el ? el.closest(".roomdiv[data-room-id]") : null;
    }

    function initRoom(room) {
        if (room.dataset.roomEditorReady === "1")
            return;
        room.dataset.roomEditorReady = "1";
        room.dataset.roomNewMode = "0";

        qsa(room, ".desk").forEach(function (desk) {
            desk.addEventListener("mouseenter", function (ev) {
                highlight(desk.dataset.roomId, desk.dataset.deskId, true);
                var tooltip = ensureTooltip();
                tooltip.innerHTML = desk.dataset.deskInfo || "";
                moveTooltip(ev);
            });
            desk.addEventListener("mousemove", moveTooltip);
            desk.addEventListener("mouseleave", function () {
                highlight(desk.dataset.roomId, desk.dataset.deskId, false);
                ensureTooltip().style.display = "none";
            });
        });

        qsa(room, ".room_presence_avatar").forEach(function (avatar) {
            avatar.addEventListener("mouseenter", function () {
                highlight(avatar.dataset.roomId, avatar.dataset.deskId, true);
            });
            avatar.addEventListener("mouseleave", function () {
                highlight(avatar.dataset.roomId, avatar.dataset.deskId, false);
            });
        });
    }

    function boot() {
        qsa(document, ".roomdiv[data-room-id]").forEach(initRoom);
    }

    document.addEventListener("click", function (ev) {
        var editToggle = ev.target.closest("[data-room-edit-toggle]");
        var newButton = ev.target.closest("[data-room-new-desk]");
        var changeMapButton = ev.target.closest("[data-room-change-map]");
        var saveButton = ev.target.closest("[data-room-save-desk]");
        var cancelButton = ev.target.closest("[data-room-cancel-desk]");
        var desk = ev.target.closest(".desk.room_desk_js");
        var room;

        if (editToggle) {
            room = findRoomFromElement(editToggle);
            if (room)
                setEditMode(room, !room.classList.contains("room_edit_mode"));
            ev.preventDefault();
            return;
        }

        if (newButton) {
            room = findRoomFromElement(newButton);
            if (room) {
                setEditMode(room, true);
                room.dataset.roomNewMode = "1";
                var hint = roomParts(room).hint;
                if (hint)
                    hint.textContent = "Clique sur le plan à l’endroit où créer le poste.";
            }
            ev.preventDefault();
            return;
        }

        if (changeMapButton) {
            room = findRoomFromElement(changeMapButton);
            if (room) {
                setEditMode(room, true);
                var mapParts = roomParts(room);
                if (mapParts.mapInput)
                    mapParts.mapInput.click();
                if (mapParts.hint)
                    mapParts.hint.textContent = "Choisis une image PNG pour remplacer le plan de la salle.";
            }
            ev.preventDefault();
            return;
        }

        if (saveButton) {
            room = findRoomFromElement(saveButton);
            var parts = roomParts(room);
            var form = parts.form;
            if (!form)
                return;
            var deskId = form.dataset.deskId;
            var data = collectEditorData(form);
            var url = deskId ? "/api/desk/" + deskId : "/api/desk";
            var method = deskId ? "PUT" : "POST";
            saveButton.disabled = true;
            postDesk(url, method, data).then(function (answer) {
                if (!answer.ok)
                    throw new Error("HTTP " + answer.status);
                window.location.reload();
            }).catch(function () {
                saveButton.disabled = false;
                if (parts.hint)
                    parts.hint.textContent = "Impossible d’enregistrer le poste.";
            });
            ev.preventDefault();
            return;
        }

        if (cancelButton) {
            room = findRoomFromElement(cancelButton);
            var cancelParts = roomParts(room);
            if (cancelParts.form) {
                cancelParts.form.classList.remove("room_editor_open");
                cancelParts.form.dataset.deskId = "";
            }
            if (cancelParts.picker)
                cancelParts.picker.value = "";
            if (cancelParts.saveButton)
                cancelParts.saveButton.disabled = true;
            room.dataset.roomNewMode = "0";
            qsa(room, ".desk.room_editor_selected").forEach(function (el) {
                el.classList.remove("room_editor_selected");
            });
            ev.preventDefault();
            return;
        }

        if (desk) {
            room = findRoomFromElement(desk);
            if (!room || !room.classList.contains("room_edit_mode"))
                return;
            fillEditor(room, desk);
            ev.preventDefault();
            ev.stopPropagation();
            return;
        }
    });

    document.addEventListener("change", function (ev) {
        var picker = ev.target.closest("[data-room-desk-picker]");
        if (!picker || !picker.value)
            return;
        var room = findRoomFromElement(picker);
        var roomId = room ? room.dataset.roomId : "";
        var desk = room ? qs(room, ".desk[data-room-id='" + roomId + "'][data-desk-id='" + picker.value + "']") : null;
        if (desk)
            fillEditor(room, desk);
    });

    document.addEventListener("mousedown", function (ev) {
        var desk = ev.target.closest(".desk.room_desk_js");
        var room = findRoomFromElement(desk);
        if (!desk || !room || !room.classList.contains("room_edit_mode"))
            return;
        ev.preventDefault();
        var rect = desk.getBoundingClientRect();
        window.roomDeskDragging = {
            room: room,
            desk: desk,
            offsetX: ev.clientX - rect.left,
            offsetY: ev.clientY - rect.top
        };
        fillEditor(room, desk);
    });

    document.addEventListener("mousemove", function (ev) {
        var dragging = window.roomDeskDragging;
        if (!dragging)
            return;
        var parts = roomParts(dragging.room);
        if (!parts.map)
            return;
        var rect = parts.map.getBoundingClientRect();
        var x = deskXFromPointer(ev.clientX, rect, dragging.offsetX);
        var y = clamp(Math.round(ev.clientY - rect.top - dragging.offsetY), 0, 460);
        setDeskPosition(dragging.desk, x, y);
        if (parts.form && parts.form.dataset.deskId === dragging.desk.dataset.deskId) {
            parts.form.elements.x.value = String(x);
            parts.form.elements.y.value = String(y);
        }
    });

    document.addEventListener("mouseup", function () {
        var dragging = window.roomDeskDragging;
        if (!dragging)
            return;
        window.roomDeskDragging = null;
        postDesk("/api/desk/" + dragging.desk.dataset.deskId, "PUT", {
            id_room: dragging.room.dataset.roomId,
            x: dragging.desk.dataset.x,
            y: dragging.desk.dataset.y
        }).catch(function () {});
    });

    document.addEventListener("click", function (ev) {
        var map = ev.target.closest("[data-room-map]");
        var room = findRoomFromElement(map);
        if (!map || !room || !room.classList.contains("room_edit_mode") || room.dataset.roomNewMode !== "1" || ev.target !== map)
            return;
        var rect = map.getBoundingClientRect();
        var x = clamp(Math.round(ev.clientX - roomDesignLeft(rect)), 40, 1250);
        var y = clamp(Math.round(ev.clientY - rect.top), 0, 460);
        fillEditor(room, null, x, y);
        room.dataset.roomNewMode = "0";
        ev.preventDefault();
    });

    if (document.readyState === "loading")
        document.addEventListener("DOMContentLoaded", boot);
    else
        boot();
}());

(function () {
    "use strict";

    function htmlToText(value) {
        var node = document.createElement("div");
        var hidden;
        var i;
        node.innerHTML = String(value || "").replace(/<br\s*\/?\s*>/gi, "\n");
        hidden = node.querySelectorAll('[style*="display: none"], [style*="display:none"]');
        for (i = 0; i < hidden.length; ++i)
            hidden[i].remove();
        return (node.textContent || node.innerText || "").trim();
    }

    function attachForm(root) {
        var inputs = Array.prototype.slice.call(root.querySelectorAll("[data-dabsic-field]"));
        var saveButton = document.getElementById("dabsic-form-save");
        var stateBox = document.getElementById("dabsic-form-state");
        var messageBox = document.getElementById("dabsic-form-message");
        var baseline;
        var outputComplete = root.getAttribute("data-output-complete") === "1";
        var saving = false;
        var savedTimer = null;
        var overrideList = document.getElementById("dabsic-form-overrides-list");
        var overrideAdd = document.getElementById("dabsic-form-override-add");

        if (!saveButton || !stateBox || !messageBox)
            return;

        function label(name, fallback) {
            return root.getAttribute("data-" + name) || fallback;
        }

        function values() {
            var out = {};
            inputs.forEach(function (input) {
                out[input.getAttribute("data-dabsic-field")] = input.value;
            });
            return out;
        }

        function overrides() {
            var out = {};
            if (!overrideList)
                return out;
            Array.prototype.forEach.call(overrideList.querySelectorAll(".dabsic-form-override-row"), function (row) {
                var key = row.querySelector(".dabsic-form-override-key").value.trim();
                var value = row.querySelector(".dabsic-form-override-value").value;
                if (key !== "")
                    out[key] = value;
            });
            return out;
        }

        function snapshot() {
            return JSON.stringify({values: values(), overrides: overrides()});
        }

        function makeOverrideRow(key, value) {
            var row = document.createElement("div");
            var keyInput = document.createElement("input");
            var valueInput = document.createElement("input");
            var remove = document.createElement("button");

            row.className = "dabsic-form-override-row";
            keyInput.className = "dabsic-form-override-key";
            keyInput.type = "text";
            keyInput.autocomplete = "off";
            keyInput.spellcheck = false;
            keyInput.placeholder = label("override-key-placeholder", "Clé Dabsic");
            keyInput.value = key || "";
            valueInput.className = "dabsic-form-override-value";
            valueInput.type = "text";
            valueInput.autocomplete = "off";
            valueInput.spellcheck = false;
            valueInput.placeholder = label("override-value-placeholder", "Valeur");
            valueInput.value = value || "";
            remove.className = "dabsic-form-override-remove";
            remove.type = "button";
            remove.textContent = label("remove-label", "Supprimer");
            remove.addEventListener("click", function () {
                row.remove();
                updateState();
            });
            row.appendChild(keyInput);
            row.appendChild(valueInput);
            row.appendChild(remove);
            return row;
        }

        baseline = snapshot();

        function isDirty() {
            return !outputComplete || snapshot() !== baseline;
        }

        function setState(kind, text) {
            stateBox.classList.remove("is-dirty", "is-saving", "is-saved");
            if (kind)
                stateBox.classList.add("is-" + kind);
            stateBox.textContent = text;
        }

        function setMessage(message, kind) {
            messageBox.textContent = message || "";
            messageBox.classList.remove("is-error", "is-success");
            if (message)
                messageBox.classList.add(kind === "success" ? "is-success" : "is-error");
        }

        function updateState() {
            var dirty = isDirty();
            root.classList.toggle("is-dirty", dirty);
            saveButton.disabled = !dirty || saving;
            if (saving)
                setState("saving", label("saving-label", "Sauvegarde…"));
            else if (dirty)
                setState("dirty", label("dirty-label", "Modifications non sauvegardées"));
            else
                setState("", label("clean-label", "Aucune modification"));
        }

        function save() {
            var sentValues;
            var sentOverrides;
            var body;

            if (saving || !isDirty())
                return;
            if (!window.confirm(label("confirm-save", "Enregistrer ces données ?")))
                return;

            sentValues = values();
            sentOverrides = overrides();
            body = new FormData();
            body.append("reference", root.getAttribute("data-reference") || "");
            body.append("mode", root.getAttribute("data-mode") || "dabsic");
            body.append("chain", root.getAttribute("data-chain") || "");
            body.append("form_role", root.getAttribute("data-form-role") || "");
            body.append("reference_hash", root.getAttribute("data-reference-hash") || "");
            body.append("output", root.getAttribute("data-output") || "");
            body.append("output_hash", root.getAttribute("data-output-hash") || "");
            body.append("output_exists", root.getAttribute("data-output-exists") || "0");
            body.append("values", JSON.stringify(sentValues));
            body.append("overrides", JSON.stringify(sentOverrides));
            body.append("overrides_hash", root.getAttribute("data-overrides-hash") || "");
            body.append("overrides_exists", root.getAttribute("data-overrides-exists") || "0");

            saving = true;
            setMessage("", "error");
            updateState();

            fetch(root.getAttribute("data-save-url"), {
                method: "POST",
                credentials: "same-origin",
                body: body
            }).then(function (response) {
                return response.text().then(function (text) {
                    var packet = null;
                    try {
                        packet = JSON.parse(text);
                    } catch (error) {
                        throw new Error(text || response.statusText || label("network-error", "Erreur réseau."));
                    }
                    if (!response.ok || !packet || packet.result !== "ok")
                        throw new Error(packet && packet.msg ? htmlToText(packet.msg) :
                            response.statusText || label("network-error", "Erreur réseau."));
                    return packet;
                });
            }).then(function (packet) {
                baseline = JSON.stringify({values: sentValues, overrides: sentOverrides});
                root.setAttribute("data-output-hash", packet.hash || root.getAttribute("data-output-hash") || "");
                root.setAttribute("data-output-exists", "1");
                root.setAttribute("data-overrides-hash", packet.overrides_hash || "");
                root.setAttribute("data-overrides-exists", packet.overrides_exists ? "1" : "0");
                root.setAttribute("data-output-complete", "1");
                outputComplete = true;
                saving = false;
                setMessage(packet.msg || label("saved-label", "Données sauvegardées."), "success");
                updateState();
                if (!isDirty()) {
                    setState("saved", label("saved-label", "Données sauvegardées."));
                    if (savedTimer)
                        window.clearTimeout(savedTimer);
                    savedTimer = window.setTimeout(updateState, 1800);
                }
            }).catch(function (error) {
                saving = false;
                setMessage(error && error.message ? error.message :
                    label("network-error", "Erreur réseau."), "error");
                updateState();
            });
        }

        if (overrideList)
            Array.prototype.forEach.call(overrideList.querySelectorAll(".dabsic-form-override-remove"), function (button) {
                button.addEventListener("click", function () {
                    button.closest(".dabsic-form-override-row").remove();
                    updateState();
                });
            });
        if (overrideAdd)
            overrideAdd.addEventListener("click", function () {
                var row = makeOverrideRow("", "");
                overrideList.appendChild(row);
                row.querySelector(".dabsic-form-override-key").focus();
                updateState();
            });

        root.addEventListener("input", updateState);
        root.addEventListener("submit", function (event) {
            event.preventDefault();
            save();
        });
        root.addEventListener("keydown", function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
                event.preventDefault();
                save();
            }
        });

        window.addEventListener("beforeunload", function (event) {
            if (!isDirty())
                return;
            event.preventDefault();
            event.returnValue = label("unsaved-warning", "Des modifications ne sont pas sauvegardées.");
            return event.returnValue;
        });

        updateState();
        if (inputs.length)
            inputs[0].focus();
    }

    var root = document.getElementById("dabsic-form-root");
    if (root)
        attachForm(root);
}());

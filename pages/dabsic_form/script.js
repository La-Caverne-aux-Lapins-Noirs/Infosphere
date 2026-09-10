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
        var previewButton = document.getElementById("dabsic-form-preview");
        var finalizeButton = document.getElementById("dabsic-form-finalize");
        var stateBox = document.getElementById("dabsic-form-state");
        var messageBox = document.getElementById("dabsic-form-message");
        var baseline;
        var outputComplete = root.getAttribute("data-output-complete") === "1";
        var saving = false;
        var savingPromise = null;
        var previewing = false;
        var finalizing = false;
        var reportFinalized = root.getAttribute("data-report-finalized") === "1";
        var signatureSaving = false;
        var savedTimer = null;
        var overrideList = document.getElementById("dabsic-form-overrides-list");
        var overrideAdd = document.getElementById("dabsic-form-override-add");
        var signatureBox = document.getElementById("dabsic-form-signature");
        var signatureEditor = document.getElementById("dabsic-form-signature-editor");
        var signatureExisting = document.getElementById("dabsic-form-signature-existing");
        var signatureCanvas = document.getElementById("dabsic-form-signature-canvas");
        var signatureCurrent = document.getElementById("dabsic-form-signature-current");
        var signatureReplace = document.getElementById("dabsic-form-signature-replace");
        var signatureClear = document.getElementById("dabsic-form-signature-clear");
        var signatureCancel = document.getElementById("dabsic-form-signature-cancel");
        var signatureSave = document.getElementById("dabsic-form-signature-save");
        var signatureState = {
            exists: signatureBox && signatureBox.getAttribute("data-signature-exists") === "1",
            editable: signatureBox && signatureBox.getAttribute("data-signature-editable") === "1",
            drawn: false,
            drawing: false,
            last: null
        };

        if (!saveButton || !stateBox || !messageBox)
            return;

        function label(name, fallback) {
            return root.getAttribute("data-" + name) || fallback;
        }

        function values() {
            var out = {};
            inputs.forEach(function (input) {
                var field = input.getAttribute("data-dabsic-field");
                if (input.type === "checkbox") {
                    if (!Object.prototype.hasOwnProperty.call(out, field))
                        out[field] = [];
                    if (input.checked)
                        out[field].push(input.value);
                } else if (input.type === "radio") {
                    if (input.checked)
                        out[field] = input.value;
                    else if (!Object.prototype.hasOwnProperty.call(out, field))
                        out[field] = "";
                } else
                    out[field] = input.value;
            });
            return out;
        }

        function euros(cents) {
            var amount = (Math.max(0, parseInt(cents, 10) || 0) / 100).toFixed(2).replace(".", ",");
            var parts = amount.split(",");
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            return parts.join(",") + " €";
        }

        function setFieldValue(field, value) {
            var input = root.querySelector('[data-dabsic-field="' + field + '"]');
            if (input)
                input.value = value;
        }

        function updateBillingAmounts() {
            var tariff = root.querySelector("[data-dabsic-billing-template]");
            var foreign = root.querySelector('[data-dabsic-billing-foreign="1"]');
            var option;
            var registration;
            var tuition;
            var advance;

            if (!tariff || !foreign)
                return;
            option = tariff.options[tariff.selectedIndex];
            if (!option || tariff.value === "" || foreign.value === "" ||
                !option.hasAttribute("data-billing-tuition")) {
                [
                    "InterviewReport.TariffTemplateName",
                    "InterviewReport.TuitionPrice",
                    "InterviewReport.TotalPrice",
                    "InterviewReport.RegistrationFee",
                    "InterviewReport.ForeignTuitionAdvance",
                    "InterviewReport.AmountDueAtRegistration",
                    "InterviewReport.TuitionBalance"
                ].forEach(function (field) { setFieldValue(field, ""); });
                return;
            }

            registration = parseInt(option.getAttribute("data-billing-registration"), 10) || 0;
            tuition = parseInt(option.getAttribute("data-billing-tuition"), 10) || 0;
            advance = foreign.value === "1" ? Math.round(tuition / 6) : 0;
            setFieldValue("InterviewReport.TariffTemplateName", option.getAttribute("data-billing-name") || "");
            setFieldValue("InterviewReport.TuitionPrice", euros(tuition));
            setFieldValue("InterviewReport.TotalPrice", euros(registration + tuition));
            setFieldValue("InterviewReport.RegistrationFee", euros(registration));
            setFieldValue("InterviewReport.ForeignTuitionAdvance", euros(advance));
            setFieldValue("InterviewReport.AmountDueAtRegistration", euros(registration + advance));
            setFieldValue("InterviewReport.TuitionBalance", euros(tuition - advance));
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
            var busy = saving || previewing || finalizing || signatureSaving;
            root.classList.toggle("is-dirty", dirty);
            saveButton.disabled = !dirty || busy;
            if (previewButton)
                previewButton.disabled = busy;
            if (finalizeButton)
                finalizeButton.disabled = busy || reportFinalized;
            if (signatureSave)
                signatureSave.disabled = signatureSaving || !signatureState.drawn;
            if (finalizing)
                setState("saving", "Génération et envoi…");
            else if (previewing)
                setState("saving", "Génération de l'aperçu…");
            else if (signatureSaving)
                setState("saving", "Enregistrement de la signature…");
            else if (saving)
                setState("saving", label("saving-label", "Sauvegarde…"));
            else if (dirty)
                setState("dirty", label("dirty-label", "Modifications non sauvegardées"));
            else
                setState("", label("clean-label", "Aucune modification"));
        }

        function save(askConfirmation) {
            var sentValues;
            var sentOverrides;
            var body;

            if (saving)
                return savingPromise || Promise.resolve(false);
            if (!isDirty())
                return Promise.resolve(true);
            if (askConfirmation !== false && !window.confirm(label("confirm-save", "Enregistrer ces données ?")))
                return Promise.resolve(false);

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

            savingPromise = fetch(root.getAttribute("data-save-url"), {
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
                savingPromise = null;
                setMessage(packet.msg || label("saved-label", "Données sauvegardées."), "success");
                updateState();
                if (!isDirty()) {
                    setState("saved", label("saved-label", "Données sauvegardées."));
                    if (savedTimer)
                        window.clearTimeout(savedTimer);
                    savedTimer = window.setTimeout(updateState, 1800);
                }
                return true;
            }).catch(function (error) {
                saving = false;
                savingPromise = null;
                setMessage(error && error.message ? error.message :
                    label("network-error", "Erreur réseau."), "error");
                updateState();
                return false;
            });
            return savingPromise;
        }

        function canvasBlob(canvas) {
            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (blob)
                        resolve(blob);
                    else
                        reject(new Error("Impossible de lire la signature tracée."));
                }, "image/png");
            });
        }

        function signaturePoint(event) {
            var rect = signatureCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * signatureCanvas.width / Math.max(1, rect.width),
                y: (event.clientY - rect.top) * signatureCanvas.height / Math.max(1, rect.height)
            };
        }

        function clearSignatureCanvas() {
            if (!signatureCanvas)
                return;
            signatureCanvas.getContext("2d").clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            signatureState.drawn = false;
            signatureState.drawing = false;
            signatureState.last = null;
            updateState();
        }

        function showSignatureEditor() {
            if (!signatureState.editable || !signatureEditor)
                return;
            if (signatureExisting)
                signatureExisting.hidden = true;
            signatureEditor.hidden = false;
            clearSignatureCanvas();
        }

        function showExistingSignature() {
            if (signatureExisting)
                signatureExisting.hidden = false;
            if (signatureEditor)
                signatureEditor.hidden = true;
            clearSignatureCanvas();
        }

        function saveSignatureIfNeeded(requireSignature) {
            var url = root.getAttribute("data-signature-url") || "";
            var previewData;

            if (!signatureBox) {
                if (requireSignature)
                    return Promise.reject(new Error("Aucune signature n'est disponible pour la personne ayant conduit l'entretien."));
                return Promise.resolve(true);
            }
            if (!signatureState.editable) {
                if (requireSignature && !signatureState.exists)
                    return Promise.reject(new Error("La signature de la personne ayant conduit l'entretien est manquante."));
                return Promise.resolve(true);
            }
            if (!signatureState.drawn) {
                if (requireSignature && !signatureState.exists)
                    return Promise.reject(new Error("Tracez votre signature avant de générer le compte rendu."));
                return Promise.resolve(true);
            }
            if (!url || !signatureCanvas)
                return Promise.reject(new Error("Le service d'enregistrement de signature n'est pas disponible."));

            previewData = signatureCanvas.toDataURL("image/png");
            signatureSaving = true;
            setMessage("", "error");
            updateState();
            return canvasBlob(signatureCanvas).then(function (blob) {
                var body = new FormData();
                body.append("signature", blob, "signature.png");
                return fetch(url, {method: "POST", credentials: "same-origin", body: body});
            }).then(function (response) {
                return response.text().then(function (text) {
                    var packet = null;
                    try {
                        packet = JSON.parse(text);
                    } catch (error) {
                        throw new Error(text || response.statusText || "L'enregistrement de la signature a échoué.");
                    }
                    if (!response.ok || !packet || packet.result !== "ok")
                        throw new Error(packet && packet.msg ? htmlToText(packet.msg) :
                            response.statusText || "L'enregistrement de la signature a échoué.");
                    return packet;
                });
            }).then(function (packet) {
                signatureState.exists = true;
                signatureState.drawn = false;
                signatureState.drawing = false;
                signatureState.last = null;
                signatureBox.setAttribute("data-signature-exists", "1");
                if (signatureCurrent) {
                    signatureCurrent.src = previewData;
                    signatureCurrent.hidden = false;
                }
                if (signatureCancel)
                    signatureCancel.hidden = false;
                showExistingSignature();
                signatureSaving = false;
                setMessage(packet.msg || "Signature enregistrée.", "success");
                updateState();
                return true;
            }).catch(function (error) {
                signatureSaving = false;
                updateState();
                throw error;
            });
        }

        function fetchPdf(url, popup, loadingText) {
            if (popup)
                popup.document.body.innerHTML = '<p style="font-family:sans-serif">' + loadingText + '</p>';
            return fetch(url, {
                method: "POST",
                credentials: "same-origin"
            }).then(function (response) {
                var contentType = response.headers.get("Content-Type") || "";
                if (response.ok && contentType.toLowerCase().indexOf("application/pdf") >= 0)
                    return response.blob();
                return response.text().then(function (text) {
                    var packet = null;
                    try {
                        packet = JSON.parse(text);
                    } catch (error) {
                        throw new Error(text || response.statusText || "La génération du PDF a échoué.");
                    }
                    throw new Error(packet && packet.msg ? htmlToText(packet.msg) :
                        response.statusText || "La génération du PDF a échoué.");
                });
            }).then(function (blob) {
                var objectUrl = URL.createObjectURL(blob);
                if (popup)
                    popup.location = objectUrl;
                else
                    window.open(objectUrl, "_blank", "noopener");
                window.setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 60000);
                return true;
            });
        }

        function previewReport() {
            var url = root.getAttribute("data-preview-url") || "";
            var popup;

            if (!url || previewing || finalizing)
                return;
            popup = window.open("", "_blank");
            if (popup)
                popup.document.write('<p style="font-family:sans-serif">Préparation de l\'aperçu…</p>');
            previewing = true;
            setMessage("", "error");
            updateState();

            save(false).then(function (saved) {
                if (!saved)
                    throw new Error("Le brouillon n'a pas pu être sauvegardé avant la prévisualisation.");
                return saveSignatureIfNeeded(true);
            }).then(function () {
                return fetchPdf(url, popup, "Génération de l'aperçu…");
            }).then(function () {
                previewing = false;
                setMessage("Aperçu généré : aucun mail n'a été envoyé et le compte rendu n'a pas été finalisé.", "success");
                updateState();
            }).catch(function (error) {
                if (popup)
                    popup.close();
                previewing = false;
                setMessage(error && error.message ? error.message :
                    "La prévisualisation du compte rendu a échoué.", "error");
                updateState();
            });
        }

        function finalizeReport() {
            var url = root.getAttribute("data-finalize-url") || "";
            var popup;

            if (!url || finalizing || previewing || reportFinalized)
                return;
            if (!window.confirm(label("confirm-finalize", "Valider, générer et envoyer ce compte rendu ?")))
                return;

            popup = window.open("", "_blank");
            if (popup)
                popup.document.write('<p style="font-family:sans-serif">Génération du compte rendu…</p>');
            finalizing = true;
            setMessage("", "error");
            updateState();

            save(false).then(function (saved) {
                if (!saved)
                    throw new Error("Le compte rendu n'a pas pu être sauvegardé avant sa validation.");
                return saveSignatureIfNeeded(true);
            }).then(function () {
                return fetchPdf(url, popup, "Génération et envoi du compte rendu…");
            }).then(function () {
                finalizing = false;
                reportFinalized = true;
                if (finalizeButton)
                    finalizeButton.value = "Compte rendu déjà envoyé";
                setMessage("Compte rendu finalisé : l'envoi a été accepté par le service mail. Rechargez la page pour consulter la trace d'envoi détaillée.", "success");
                updateState();
                try {
                    if (window.opener && !window.opener.closed)
                        window.opener.location.reload();
                } catch (error) {
                    // The PDF/report stays valid even if the opener cannot be refreshed.
                }
            }).catch(function (error) {
                if (popup)
                    popup.close();
                finalizing = false;
                setMessage(error && error.message ? error.message :
                    "La génération du compte rendu a échoué.", "error");
                updateState();
            });
        }

        if (signatureCanvas && signatureState.editable) {
            var signatureContext = signatureCanvas.getContext("2d");
            signatureContext.lineWidth = 3;
            signatureContext.lineCap = "round";
            signatureContext.lineJoin = "round";
            signatureContext.strokeStyle = "#000";

            signatureCanvas.addEventListener("pointerdown", function (event) {
                var point;
                if (event.button !== undefined && event.button !== 0)
                    return;
                event.preventDefault();
                point = signaturePoint(event);
                signatureState.drawing = true;
                signatureState.last = point;
                try { signatureCanvas.setPointerCapture(event.pointerId); } catch (ignore) {}
            });
            signatureCanvas.addEventListener("pointermove", function (event) {
                var point;
                if (!signatureState.drawing)
                    return;
                event.preventDefault();
                point = signaturePoint(event);
                signatureContext.beginPath();
                signatureContext.moveTo(signatureState.last.x, signatureState.last.y);
                signatureContext.lineTo(point.x, point.y);
                signatureContext.stroke();
                signatureState.last = point;
                signatureState.drawn = true;
                updateState();
            });
            ["pointerup", "pointercancel"].forEach(function (name) {
                signatureCanvas.addEventListener(name, function (event) {
                    if (!signatureState.drawing)
                        return;
                    signatureState.drawing = false;
                    signatureState.last = null;
                    try { signatureCanvas.releasePointerCapture(event.pointerId); } catch (ignore) {}
                    updateState();
                });
            });
        }
        if (signatureReplace)
            signatureReplace.addEventListener("click", showSignatureEditor);
        if (signatureClear)
            signatureClear.addEventListener("click", clearSignatureCanvas);
        if (signatureCancel)
            signatureCancel.addEventListener("click", showExistingSignature);
        if (signatureSave)
            signatureSave.addEventListener("click", function () {
                saveSignatureIfNeeded(true).catch(function (error) {
                    setMessage(error && error.message ? error.message : "L'enregistrement de la signature a échoué.", "error");
                    updateState();
                });
            });
        if (previewButton)
            previewButton.addEventListener("click", previewReport);

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
        if (finalizeButton)
            finalizeButton.addEventListener("click", finalizeReport);

        root.addEventListener("input", updateState);
        root.addEventListener("change", function (event) {
            if (event.target.matches("[data-dabsic-billing-template], [data-dabsic-billing-foreign=\"1\"]"))
                updateBillingAmounts();
            updateState();
        });
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
            if (!isDirty() && !signatureState.drawn)
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

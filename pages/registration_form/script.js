(function () {
    "use strict";

    var cancelForm = document.getElementById("registration-event-cancel-form");
    if (cancelForm) {
        var cancelButton = cancelForm.querySelector("[data-event-cancel]");
        var cancelMessage = cancelForm.querySelector(".registration-event-cancel-message");
        if (cancelButton)
            cancelButton.addEventListener("click", async function () {
                if (!window.confirm("Annuler votre inscription à cet évènement ?"))
                    return;
                cancelButton.disabled = true;
                try {
                    var body = new URLSearchParams();
                    body.set("token", cancelForm.getAttribute("data-token") || "");
                    var response = await fetch(cancelForm.getAttribute("data-url"), {
                        method: "POST",
                        headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
                        body: body.toString()
                    });
                    var packet = await response.json();
                    if (!response.ok || !packet || packet.result !== "ok")
                        throw new Error(packet && packet.msg ? packet.msg.replace(/<[^>]+>/g, "") : response.statusText);
                    window.location.reload();
                } catch (error) {
                    if (cancelMessage)
                        cancelMessage.textContent = error && error.message ? error.message : "Erreur réseau.";
                    cancelButton.disabled = false;
                }
            });
    }

    var form = document.getElementById("registration-form");
    if (!form)
        return;

    var page = form.closest(".registration-form-page");
    var message = document.getElementById("registration-form-message");
    var saving = false;
    var signatureStates = {};
    var paraphSection = form.querySelector(".registration-paraph");
    var paraphState = null;
    var showExistingParaph = null;
    var baseline = "";

    function htmlToText(value) {
        var box = document.createElement("div");
        box.innerHTML = value || "";
        return box.textContent || box.innerText || "";
    }

    function fieldValues() {
        var out = {};
        form.querySelectorAll("[data-registration-field]").forEach(function (element) {
            var field = element.getAttribute("data-registration-field");
            if (element.type === "checkbox") {
                if (!Object.prototype.hasOwnProperty.call(out, field))
                    out[field] = [];
                if (element.checked)
                    out[field].push(element.value);
            } else if (element.type === "radio") {
                if (element.checked)
                    out[field] = element.value;
                else if (!Object.prototype.hasOwnProperty.call(out, field))
                    out[field] = "";
            } else
                out[field] = element.value;
        });
        return out;
    }

    function signatureKey(group) {
        return group.replace(/[^a-zA-Z0-9_-]+/g, "_");
    }

    function snapshot() {
        var signatures = {};
        Object.keys(signatureStates).forEach(function (group) {
            signatures[group] = {
                drawn: signatureStates[group].drawn,
                deleted: signatureStates[group].deleted,
                useProfile: signatureStates[group].useProfile
            };
        });
        return JSON.stringify({
            values: fieldValues(),
            signatures: signatures,
            paraph: paraphState ? {drawn: paraphState.drawn, editing: paraphState.editing} : null
        });
    }

    function setMessage(text, success) {
        message.textContent = text || "";
        message.className = "registration-form-message" +
            (text ? (success ? " is-success" : " is-error") : "");
    }

    function updateState() {
        page.classList.toggle("is-dirty", snapshot() !== baseline);
        page.classList.toggle("is-saving", saving);
    }

    form.querySelectorAll(".registration-signature[data-signature-group]").forEach(function (section) {
        var group = section.getAttribute("data-signature-group");
        var canvas = section.querySelector("canvas");
        var context = canvas.getContext("2d");
        var state = {
            canvas: canvas,
            drawn: false,
            deleted: false,
            useProfile: false,
            drawing: false,
            last: null,
            existing: section.getAttribute("data-existing") === "1"
        };

        signatureStates[group] = state;
        context.lineWidth = 3;
        context.lineCap = "round";
        context.lineJoin = "round";
        context.strokeStyle = "#000000";

        function position(event) {
            var rect = canvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * canvas.width / rect.width,
                y: (event.clientY - rect.top) * canvas.height / rect.height
            };
        }

        canvas.addEventListener("pointerdown", function (event) {
            event.preventDefault();
            canvas.setPointerCapture(event.pointerId);
            state.drawing = true;
            state.deleted = false;
            state.useProfile = false;
            section.classList.remove("is-profile-signature");
            var profileStatus = section.querySelector("[data-profile-signature-status]");
            if (profileStatus)
                profileStatus.textContent = "";
            state.last = position(event);
        });

        canvas.addEventListener("pointermove", function (event) {
            var point;
            if (!state.drawing)
                return;
            event.preventDefault();
            point = position(event);
            context.beginPath();
            context.moveTo(state.last.x, state.last.y);
            context.lineTo(point.x, point.y);
            context.stroke();
            state.last = point;
            state.drawn = true;
            updateState();
        });

        function stopDrawing(event) {
            if (!state.drawing)
                return;
            state.drawing = false;
            try {
                canvas.releasePointerCapture(event.pointerId);
            } catch (ignore) {
                // The pointer may already have been released by the browser.
            }
            updateState();
        }

        canvas.addEventListener("pointerup", stopDrawing);
        canvas.addEventListener("pointercancel", stopDrawing);

        section.querySelector("[data-clear-signature]").addEventListener("click", function () {
            context.clearRect(0, 0, canvas.width, canvas.height);
            state.drawn = false;
            state.deleted = state.existing;
            state.useProfile = false;
            section.classList.remove("is-profile-signature");
            var profileStatus = section.querySelector("[data-profile-signature-status]");
            if (profileStatus)
                profileStatus.textContent = "";
            updateState();
        });

        var useProfileButton = section.querySelector("[data-use-profile-signature]");
        if (useProfileButton)
            useProfileButton.addEventListener("click", function () {
                context.clearRect(0, 0, canvas.width, canvas.height);
                state.drawn = false;
                state.deleted = false;
                state.useProfile = true;
                section.classList.add("is-profile-signature");
                var profileStatus = section.querySelector("[data-profile-signature-status]");
                if (profileStatus)
                    profileStatus.textContent = "Signature enregistrée sélectionnée.";
                updateState();
            });
    });

    if (paraphSection) {
        var paraphCanvas = paraphSection.querySelector("canvas");
        var paraphContext = paraphCanvas.getContext("2d");
        var paraphExistingBox = paraphSection.querySelector("[data-paraph-existing-box]");
        var paraphEditor = paraphSection.querySelector("[data-paraph-editor]");
        var paraphCurrent = paraphSection.querySelector("[data-paraph-current]");
        var paraphReplace = paraphSection.querySelector("[data-replace-paraph]");
        var paraphClear = paraphSection.querySelector("[data-clear-paraph]");
        var paraphCancel = paraphSection.querySelector("[data-cancel-paraph]");
        paraphState = {
            canvas: paraphCanvas,
            existing: paraphSection.getAttribute("data-paraph-existing") === "1",
            drawn: false,
            drawing: false,
            editing: paraphSection.getAttribute("data-paraph-existing") !== "1",
            last: null,
            lastDataUrl: ""
        };
        paraphContext.lineWidth = 3;
        paraphContext.lineCap = "round";
        paraphContext.lineJoin = "round";
        paraphContext.strokeStyle = "#000000";

        function paraphPosition(event) {
            var rect = paraphCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * paraphCanvas.width / Math.max(1, rect.width),
                y: (event.clientY - rect.top) * paraphCanvas.height / Math.max(1, rect.height)
            };
        }

        function clearParaphCanvas() {
            paraphContext.clearRect(0, 0, paraphCanvas.width, paraphCanvas.height);
            paraphState.drawn = false;
            paraphState.drawing = false;
            paraphState.last = null;
        }

        function showParaphEditor() {
            paraphState.editing = true;
            if (paraphExistingBox)
                paraphExistingBox.hidden = true;
            if (paraphEditor)
                paraphEditor.hidden = false;
            clearParaphCanvas();
            updateState();
        }

        showExistingParaph = function () {
            paraphState.editing = false;
            if (paraphExistingBox)
                paraphExistingBox.hidden = false;
            if (paraphEditor)
                paraphEditor.hidden = true;
            clearParaphCanvas();
            updateState();
        };

        paraphCanvas.addEventListener("pointerdown", function (event) {
            event.preventDefault();
            paraphCanvas.setPointerCapture(event.pointerId);
            paraphState.drawing = true;
            paraphState.last = paraphPosition(event);
        });
        paraphCanvas.addEventListener("pointermove", function (event) {
            var point;
            if (!paraphState.drawing)
                return;
            event.preventDefault();
            point = paraphPosition(event);
            paraphContext.beginPath();
            paraphContext.moveTo(paraphState.last.x, paraphState.last.y);
            paraphContext.lineTo(point.x, point.y);
            paraphContext.stroke();
            paraphState.last = point;
            paraphState.drawn = true;
            updateState();
        });
        function stopParaphDrawing(event) {
            if (!paraphState.drawing)
                return;
            paraphState.drawing = false;
            try { paraphCanvas.releasePointerCapture(event.pointerId); } catch (ignore) {}
            updateState();
        }
        paraphCanvas.addEventListener("pointerup", stopParaphDrawing);
        paraphCanvas.addEventListener("pointercancel", stopParaphDrawing);
        if (paraphClear)
            paraphClear.addEventListener("click", function () {
                clearParaphCanvas();
                updateState();
            });
        if (paraphReplace)
            paraphReplace.addEventListener("click", showParaphEditor);
        if (paraphCancel)
            paraphCancel.addEventListener("click", showExistingParaph);
    }

    function canvasBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob)
                    resolve(blob);
                else
                    reject(new Error(form.getAttribute("data-network-error")));
            }, "image/png");
        });
    }

    function acceptCurrentState() {
        Object.keys(signatureStates).forEach(function (group) {
            var state = signatureStates[group];
            if (state.drawn || state.useProfile)
                state.existing = true;
            if (state.deleted)
                state.existing = false;
            state.drawn = false;
            state.deleted = false;
            state.useProfile = false;
        });
        if (paraphState && paraphState.drawn) {
            paraphState.lastDataUrl = paraphState.canvas.toDataURL("image/png");
            paraphState.existing = true;
            paraphSection.classList.add("is-existing");
            if (paraphCurrent) {
                paraphCurrent.src = paraphState.lastDataUrl;
                paraphCurrent.hidden = false;
            }
            if (paraphCancel)
                paraphCancel.hidden = false;
            if (showExistingParaph)
                showExistingParaph();
        }
        baseline = snapshot();
        updateState();
    }

    async function send(finalize) {
        var body;
        var deleted = [];
        var response;
        var text;
        var packet;

        if (saving)
            return;
        var consent = document.getElementById("registration-signature-consent");
        if (finalize && consent && !consent.checked) {
            setMessage("Vous devez confirmer la déclaration de signature avant la validation définitive.", false);
            consent.focus();
            return;
        }
        if (finalize && !window.confirm(form.getAttribute("data-confirm-final")))
            return;

        saving = true;
        setMessage("");
        updateState();

        try {
            body = new FormData();
            body.append("token", form.getAttribute("data-token"));
            body.append("values", JSON.stringify(fieldValues()));

            for (const group of Object.keys(signatureStates)) {
                var state = signatureStates[group];
                if (state.deleted)
                    deleted.push(group);
                if (state.drawn) {
                    var blob = await canvasBlob(state.canvas);
                    body.append("signature[" + signatureKey(group) + "]", blob, "signature.png");
                }
                if (group === "DocumentSignature" && state.useProfile)
                    body.append("reuse_profile_signature", "1");
            }
            if (paraphState && paraphState.drawn) {
                var paraphBlob = await canvasBlob(paraphState.canvas);
                body.append("paraph", paraphBlob, "paraph.png");
            }
            body.append("delete_signatures", JSON.stringify(deleted));
            if (finalize && consent && consent.checked)
                body.append("signature_consent", "1");

            response = await fetch(
                finalize ? form.getAttribute("data-final-url") : form.getAttribute("data-save-url"),
                {method: "POST", credentials: "same-origin", body: body}
            );
            text = await response.text();
            try {
                packet = JSON.parse(text);
            } catch (error) {
                throw new Error(text || response.statusText);
            }
            if (!response.ok || !packet || packet.result !== "ok")
                throw new Error(packet && packet.msg ? htmlToText(packet.msg) : response.statusText);

            acceptCurrentState();
            setMessage(packet.msg || "Enregistré.", true);

            if (packet.redirect) {
                window.location.href = packet.redirect;
                return;
            }
            if (packet.completed) {
                window.location.reload();
                return;
            }
            if (packet.refresh) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 500);
            }
        } catch (error) {
            setMessage(error && error.message ? error.message : form.getAttribute("data-network-error"), false);
        } finally {
            saving = false;
            updateState();
        }
    }

    if (window.InfosphereInternshipCalendar)
        window.InfosphereInternshipCalendar.attach(form, "data-registration-field", function (hidden) {
            hidden.dispatchEvent(new Event("input", {bubbles: true}));
        });

    var eventSession = form.querySelector("[data-event-session]");
    var eventTeam = form.querySelector("[data-event-team]");
    function syncEventTeams() {
        if (!eventSession || !eventTeam)
            return;
        var selectedSession = eventSession.value;
        var selectedOption = eventTeam.options[eventTeam.selectedIndex];
        Array.prototype.forEach.call(eventTeam.options, function (option) {
            var optionSession = option.getAttribute("data-event-session");
            var visible = !optionSession || (selectedSession && optionSession === selectedSession);
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (selectedOption && selectedOption.disabled)
            eventTeam.value = "new";
    }
    if (eventSession && eventTeam) {
        eventSession.addEventListener("change", syncEventTeams);
        syncEventTeams();
    }

    baseline = snapshot();
    form.addEventListener("input", updateState);
    form.addEventListener("submit", function (event) {
        event.preventDefault();
    });
    var saveButton = document.getElementById("registration-save");
    if (saveButton)
        saveButton.addEventListener("click", function () {
            send(false);
        });
    document.getElementById("registration-finalize").addEventListener("click", function () {
        send(true);
    });
    window.addEventListener("beforeunload", function (event) {
        if (snapshot() === baseline)
            return;
        event.preventDefault();
        event.returnValue = "";
    });
    updateState();
}());

(function () {
    "use strict";

    var form = document.getElementById("registration-form");
    if (!form)
        return;

    var page = form.closest(".registration-form-page");
    var message = document.getElementById("registration-form-message");
    var saving = false;
    var signatureStates = {};
    var baseline = "";

    function htmlToText(value) {
        var box = document.createElement("div");
        box.innerHTML = value || "";
        return box.textContent || box.innerText || "";
    }

    function fieldValues() {
        var out = {};
        form.querySelectorAll("[data-registration-field]").forEach(function (element) {
            out[element.getAttribute("data-registration-field")] = element.value;
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
                deleted: signatureStates[group].deleted
            };
        });
        return JSON.stringify({values: fieldValues(), signatures: signatures});
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
            updateState();
        });
    });

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
            if (state.drawn)
                state.existing = true;
            if (state.deleted)
                state.existing = false;
            state.drawn = false;
            state.deleted = false;
        });
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

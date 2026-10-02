(function ()
{
    "use strict";

    const bridgeBase = "http://127.0.0.1:38421";
    const nfcBrowserVersion = "6.1";
    const autoPresenceInterval = 1000;
    const autoPresenceBackoff = 5000;
    let manualOperations = 0;
    let autoPresenceTimer = null;
    let autoPresenceKnown = false;
    let autoPresence = false;
    let autoReading = false;
    let autoIdentifyEndpoint = "";

    function manualOperationStart()
    {
        ++manualOperations;
    }

    function manualOperationEnd()
    {
        manualOperations = Math.max(0, manualOperations - 1);
    }

    function statusNode(source)
    {
        let root = source && source.closest
            ? source.closest("[data-nfc-container], .profile_nfc_card") : null;

        if (!root)
            return null;
        return root.querySelector("[data-nfc-status], .profile_nfc_browser_status");
    }

    function setStatus(source, text, isError)
    {
        let node = statusNode(source);
        if (!node)
            return;
        node.textContent = text || "";
        node.classList.toggle("error", !!isError);
        node.hidden = !text;
    }

    function bridgeRequest(path, options)
    {
        let opts = Object.assign({
            method: "GET",
            mode: "cors",
            cache: "no-store",
            credentials: "omit"
        }, options || {});
        let url = bridgeBase + path;

        // Chromium's Local Network Access API understands targetAddressSpace.
        // Firefox ignores unknown RequestInit members, so both browsers use the
        // same code path.
        opts.targetAddressSpace = "loopback";
        return fetch(url, opts).then(function(response)
        {
            if (!response.ok)
                throw new Error("Pont NFC: HTTP " + response.status);
            return response.json();
        });
    }

    function resultText(result, fallback)
    {
        let output = result && typeof result.output === "string" ? result.output.trim() : "";
        let bridgeVersion = result && result.bridge_version !== undefined
            ? Number(result.bridge_version) : 0;
        let exitCode = result && result.exit_code !== undefined
            ? result.exit_code : "?";
        let header = "NFC web v" + nfcBrowserVersion
            + " / pont v" + (bridgeVersion || "?")
            + " / efrits-nfc code " + exitCode;

        if (output)
            return header + "\n" + output;
        return header + "\n" + fallback + " — aucune sortie n'a été récupérée depuis efrits-nfc.";
    }

    function requireBridgeVersion(minimum)
    {
        return bridgeRequest("/v1/ping").then(function(result)
        {
            let version = result && Number.isFinite(Number(result.version)) ? Number(result.version) : 0;
            if (!result || !result.ok)
                throw new Error("Le pont NFC local ne répond pas correctement.");
            if (version < minimum)
                throw new Error("Une ancienne version du pont NFC est encore lancée (version "
                    + version + ", version " + minimum + " requise). Redémarre efrits-nfc-bridge.");
            return result;
        });
    }

    function bridgeErrorMessage(error)
    {
        let suffix = error && error.message ? " (" + error.message + ")" : "";
        return "Le pont NFC local est indisponible. Vérifie que efrits-nfc-bridge est installé et lancé. "
            + "Sous Chrome/Chromium, autorise aussi l’accès au réseau local si le navigateur le demande." + suffix;
    }

    function setBusy(source, busy)
    {
        if (source && "disabled" in source)
            source.disabled = !!busy;
    }

    function arrayBufferToBase64(buffer)
    {
        let bytes = new Uint8Array(buffer);
        let binary = "";
        let i;

        for (i = 0; i < bytes.length; ++i)
            binary += String.fromCharCode(bytes[i]);
        return btoa(binary);
    }

    function tokenFromReadOutput(output)
    {
        let match = typeof output === "string"
            ? output.match(/^Jeton\s*:\s*([0-9A-Fa-f][0-9A-Fa-f\s:-]*)$/m)
            : null;
        let token;

        if (!match)
            return "";
        // efrits-nfc affiche le jeton octet par octet ("6A A3 ...").
        // L'API Infosphere attend les 16 octets sous forme de 32 digits hex.
        token = match[1].replace(/[^0-9A-Fa-f]/g, "").toLowerCase();
        return /^[0-9a-f]{32}$/.test(token) ? token : "";
    }

    function ownerLabel(result)
    {
        let name = result && result.owner_name ? String(result.owner_name).trim() : "";
        let codename = result && result.owner_codename ? String(result.owner_codename).trim() : "";
        let id = result && result.owner_id ? parseInt(result.owner_id, 10) : 0;
        let label = name || codename || "utilisateur inconnu";

        if (codename && codename !== label)
            label += " (" + codename + ")";
        if (id)
            label += " #" + id;
        if (result && result.owner_active === false)
            label += " — compte inactif";
        return label;
    }

    function apiOwnerRequest(url, token)
    {
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({token: token})
        }).then(function(response)
        {
            if (!response.ok)
                throw new Error("Infosphère a refusé l'identification NFC (HTTP " + response.status + ").");
            return response.json();
        }).then(function(result)
        {
            if (!result || result.result !== "ok")
                throw new Error(result && result.msg ? result.msg : "Réponse invalide d'Infosphère.");
            return result;
        });
    }

    function checkCardOwner(userId, token)
    {
        return apiOwnerRequest("/api/user/" + userId + "/nfc_card_owner", token);
    }

    function identifyCardOwner(schoolId, token)
    {
        return apiOwnerRequest("/api/school/" + schoolId + "/nfc_card_owner", token);
    }

    function autoIdentifyUrl()
    {
        return autoIdentifyEndpoint;
    }

    function loadAutoIdentifyContext()
    {
        return fetch("/api/user/-1/nfc_auto_context", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store"
        }).then(function(response)
        {
            if (!response.ok)
                return null;
            return response.json();
        }).then(function(result)
        {
            if (!result || result.result !== "ok" || result.enabled !== true
                || typeof result.identify_url !== "string")
                return false;
            autoIdentifyEndpoint = result.identify_url;
            return autoIdentifyEndpoint !== "";
        }).catch(function()
        {
            // Automatic NFC is optional. Never let its discovery break the UI.
            return false;
        });
    }

    function nfcNotificationHtml(message)
    {
        return String(message == null ? "" : message)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\n/g, "<br />");
    }

    function showNfcNotification(message, isError)
    {
        // Reuse Infosphère's standard top-right feedback box, the same one
        // used by send_ajax()/silent_submit(): green on success, red on error.
        if (typeof set_error_div === "function")
            set_error_div(nfcNotificationHtml(message), !isError);
        else if (isError)
            console.error(message);
        else
            console.log(message);

        if (document.hidden && "Notification" in window && Notification.permission === "granted")
        {
            try
            {
                new Notification("Infosphère — carte NFC", {body: message});
            }
            catch (error)
            {
                // The standard Infosphère feedback box remains the fallback.
            }
        }
    }

    function autoReadCard()
    {
        let url = autoIdentifyUrl();
        let readText = "";

        if (!url || autoReading || manualOperations > 0)
            return Promise.resolve();
        autoReading = true;
        return bridgeRequest("/v1/read", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: "{}"
        }).then(function(result)
        {
            let token;

            readText = resultText(result, "La lecture NFC a échoué");
            if (!result || !result.ok)
                throw new Error(readText);
            token = tokenFromReadOutput(result.output);
            if (!token)
            {
                showNfcNotification("Carte NFC détectée, mais son contenu n'est pas une carte EFRITS valide.", true);
                return null;
            }
            return apiOwnerRequest(url, token);
        }).then(function(owner)
        {
            if (owner === null)
                return;
            if (!owner.owner_found)
            {
                showNfcNotification("Carte EFRITS valide détectée, mais aucun propriétaire n'est connu dans Infosphère.", true);
                return;
            }
            showNfcNotification("Carte NFC : " + ownerLabel(owner), false);
        }).catch(function(error)
        {
            let message = error && error.message ? error.message : "Lecture NFC impossible.";
            if (/Failed to fetch|NetworkError|Load failed/i.test(message))
                return;
            showNfcNotification("Carte détectée, mais la lecture automatique a échoué.\n" + message, true);
        }).finally(function()
        {
            autoReading = false;
        });
    }

    function scheduleAutoPresence(delay)
    {
        if (autoPresenceTimer !== null)
            window.clearTimeout(autoPresenceTimer);
        autoPresenceTimer = window.setTimeout(pollAutoPresence, delay);
    }

    function pollAutoPresence()
    {
        if (!autoIdentifyUrl())
            return;
        if (manualOperations > 0 || autoReading)
        {
            scheduleAutoPresence(350);
            return;
        }

        bridgeRequest("/v1/presence").then(function(result)
        {
            let present;

            if (!result || !result.ok || Number(result.bridge_version || 0) < 6
                || result.reader_available === false)
            {
                autoPresenceKnown = false;
                scheduleAutoPresence(autoPresenceBackoff);
                return;
            }
            present = !!result.present;
            if (!autoPresenceKnown)
            {
                autoPresenceKnown = true;
                autoPresence = present;
                if (present)
                    autoReadCard();
            }
            else
            {
                if (present && !autoPresence)
                    autoReadCard();
                autoPresence = present;
            }
            scheduleAutoPresence(autoPresenceInterval);
        }).catch(function()
        {
            autoPresenceKnown = false;
            scheduleAutoPresence(autoPresenceBackoff);
        });
    }

    function readCard(source, ownerRequest, expectedUser)
    {
        let readText = "";

        manualOperationStart();
        setBusy(source, true);
        setStatus(source, "NFC web v6 — présente une carte sur le lecteur…", false);
        return requireBridgeVersion(5).then(function()
        {
            return bridgeRequest("/v1/read", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: "{}"
            });
        }).then(function(result)
        {
            let token;

            readText = resultText(result, "La lecture NFC a échoué");
            if (!result || !result.ok)
                throw new Error(readText);
            token = tokenFromReadOutput(result.output);
            if (!token)
            {
                setStatus(source, readText + "\n\n✗ Cette carte n'est pas une carte EFRITS valide et ne peut pas être rattachée à un utilisateur.", true);
                return null;
            }
            setStatus(source, readText + "\n\nIdentification du propriétaire dans Infosphère…", false);
            return ownerRequest(token);
        }).then(function(owner)
        {
            if (owner === null)
                return;
            if (!owner.owner_found)
            {
                setStatus(source, readText + "\n\n⚠ Carte EFRITS valide, mais aucun utilisateur d'Infosphère ne possède actuellement ce code.", true);
                return;
            }
            if (expectedUser === true && owner.belongs_to_user)
            {
                setStatus(source, readText + "\n\n✓ Cette carte appartient bien à " + ownerLabel(owner) + ".", false);
                return;
            }
            if (expectedUser === true)
            {
                setStatus(source, readText + "\n\n✗ Cette carte n'appartient pas à cet élève. Elle appartient à " + ownerLabel(owner) + ".", true);
                return;
            }
            setStatus(source, readText + "\n\n✓ Carte identifiée : " + ownerLabel(owner) + ".", false);
        }).catch(function(error)
        {
            let message = error && error.message ? error.message : bridgeErrorMessage(error);
            if (/Failed to fetch|NetworkError|Load failed/i.test(message))
                message = bridgeErrorMessage(error);
            if (readText && message.indexOf(readText) !== 0)
                message = readText + "\n\nLecture réussie, mais identification du propriétaire impossible : " + message;
            setStatus(source, message, true);
        }).finally(function()
        {
            setBusy(source, false);
            manualOperationEnd();
        });
    }

    window.infosphere_nfc_bridge_ping = function(source)
    {
        setStatus(source, "Connexion au lecteur NFC…", false);
        return bridgeRequest("/v1/ping").then(function(result)
        {
            if (!result || !result.ok)
                throw new Error("réponse invalide");
            setStatus(source, "Pont NFC prêt.", false);
            return result;
        }).catch(function(error)
        {
            setStatus(source, bridgeErrorMessage(error), true);
            throw error;
        });
    };

    window.infosphere_nfc_read = function(source, userId)
    {
        let id = parseInt(userId, 10);

        if (!id)
        {
            setStatus(source, "Utilisateur NFC invalide.", true);
            return Promise.resolve();
        }
        return readCard(source, function(token) {
            return checkCardOwner(id, token);
        }, true);
    };

    window.infosphere_nfc_identify = function(source, schoolId)
    {
        let id = parseInt(schoolId, 10);

        if (!id)
        {
            setStatus(source, "École NFC invalide.", true);
            return Promise.resolve();
        }
        return readCard(source, function(token) {
            return identifyCardOwner(id, token);
        }, false);
    };

    window.infosphere_nfc_program = function(source, userId)
    {
        let id = parseInt(userId, 10);

        if (!id)
        {
            setStatus(source, "Utilisateur NFC invalide.", true);
            return Promise.resolve();
        }
        manualOperationStart();
        setBusy(source, true);
        setStatus(source, "NFC web v6 — préparation du code NFC…", false);
        return requireBridgeVersion(5).then(function()
        {
            return fetch("/api/user/" + id + "/nfc_card", {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store"
            });
        }).then(function(response)
        {
            if (!response.ok)
                throw new Error("Impossible de récupérer le code NFC dans Infosphère.");
            return response.arrayBuffer();
        }).then(function(buffer)
        {
            if (buffer.byteLength !== 32)
                throw new Error("Le fichier NFC généré par Infosphère n'a pas la taille attendue (32 octets).");
            setStatus(source, "Présente la carte à programmer sur le lecteur…", false);
            return bridgeRequest("/v1/write", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({payload: arrayBufferToBase64(buffer)})
            });
        }).then(function(result)
        {
            let text = resultText(result, "La programmation NFC a échoué");
            if (!result || !result.ok)
                throw new Error(text);
            setStatus(source, resultText(result, "Carte programmée et vérifiée."), false);
        }).catch(function(error)
        {
            let message = error && error.message ? error.message : "Erreur NFC.";
            if (/Failed to fetch|NetworkError|Load failed/i.test(message))
                message = bridgeErrorMessage(error);
            setStatus(source, message, true);
        }).finally(function()
        {
            setBusy(source, false);
            manualOperationEnd();
        });
    };

    window.addEventListener("load", function()
    {
        loadAutoIdentifyContext().then(function(enabled)
        {
            if (enabled)
                scheduleAutoPresence(500);
        });
    });
})();

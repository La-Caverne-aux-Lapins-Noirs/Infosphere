<?php
if (!can_manage_corrections())
{
    http_response_code(404);
    die();
}
require_once ("tools/correction_catalog.php");
require_once (__DIR__."/style.php");
?>
<div class="correction-admin">
    <div class="correction-heading">
        <h2>Corrections</h2>
        <p>Catalogue des bibliothèques de correction, évaluateurs de fonction et scénarios.</p>
    </div>
    <div class="correction-actions">
            <form method="post" action="/api/correction" onsubmit="return silent_submit(this, 'correction-catalog');">
                <input type="hidden" name="action" value="inventory" />
                <button type="submit">Comparer avec Distrans</button>
            </form>
            <form method="post" action="/api/correction" onsubmit="return correctionConfirmSync(this);">
                <input type="hidden" name="action" value="synchronize" />
                <button type="submit">Synchroniser vers Distrans</button>
            </form>
            <form method="post" action="/api/correction" onsubmit="return correctionConfirmRollback(this);">
                <input type="hidden" name="action" value="rollback" />
                <button type="submit" class="danger">Restaurer la génération précédente</button>
            </form>
            <form class="correction-upload" method="post" enctype="multipart/form-data" data-direct-file-upload="1" action="/api/correction" onsubmit="return silent_submit(this, 'correction-catalog');">
                <input type="hidden" name="action" value="upload" />
                <select name="id_category" required>
                    <?php foreach (correction_categories() as $category) { ?>
                        <option value="<?=$category['id']; ?>"><?=htmlspecialchars(($category['parent_name'] ? $category['parent_name'].' / ' : '').$category['name']); ?></option>
                    <?php } ?>
                </select>
                <input type="file" name="file[]" multiple required />
                <button type="submit">Mettre en ligne</button>
            </form>
            <form class="correction-category" method="post" action="/api/correction" onsubmit="return silent_submit(this, 'correction-catalog');">
                <input type="hidden" name="action" value="category" />
                <input type="text" name="name" placeholder="Nouvelle catégorie" required />
                <select name="id_parent">
                    <option value="">À la racine</option>
                    <?php foreach (correction_categories() as $category) { ?>
                        <option value="<?=$category['id']; ?>"><?=htmlspecialchars(($category['parent_name'] ? $category['parent_name'].' / ' : '').$category['name']); ?></option>
                    <?php } ?>
                </select>
                <button type="submit">Créer</button>
            </form>
    </div>
    <div id="correction-catalog">
        <?php require (__DIR__."/catalog.phtml"); ?>
    </div>
</div>

<script>
function correctionRestoreActiveTab() {
    window.setTimeout(function () {
        var root = document.getElementById("correction-catalog");
        var active = localStorage.getItem("correction-catalog-tabs");
        var button;
        var panels;
        var buttons;

        if (!root || !active)
            return;
        button = root.querySelector('[data-tabpanel-button][data-tabpanel-tab="' + active + '"]');
        if (!button)
            return;

        panels = root.querySelectorAll("[data-tabpanel-content]");
        panels.forEach(function (panel) {
            panel.style.display = panel.getAttribute("data-tabpanel-tab") === active ? "block" : "none";
        });
        buttons = root.querySelectorAll("[data-tabpanel-button]");
        buttons.forEach(function (tabButton) {
            tabButton.classList.toggle("selected", tabButton === button);
        });
    }, 0);
}

function correctionToggleLibrary(button) {
    var card = button.closest ? button.closest("[data-library-card]") : null;
    var expanded;

    if (!card)
        return false;
    expanded = button.getAttribute("aria-expanded") !== "false";
    expanded = !expanded;
    button.setAttribute("aria-expanded", expanded ? "true" : "false");
    card.classList.toggle("collapsed", !expanded);
    return false;
}

function correctionLibrariesSetExpanded(expanded) {
    var root = document.getElementById("correction-catalog");

    if (!root)
        return false;
    root.querySelectorAll("[data-library-card]").forEach(function (card) {
        var button = card.querySelector(".correction-library-toggle");
        card.classList.toggle("collapsed", !expanded);
        if (button)
            button.setAttribute("aria-expanded", expanded ? "true" : "false");
    });
    return false;
}

function correctionMoveAsset(button) {
    var form = button.closest ? button.closest("form") : null;

    if (!form) {
        console.error("Formulaire de déplacement introuvable.");
        return false;
    }
    return silent_submitf(form, {
        tofill: "correction-catalog",
        after_success: correctionRestoreActiveTab
    });
}
function correctionClearStaleDebug() {
    var debugbox = document.getElementById("debugbox");

    if (!debugbox)
        return;
    debugbox.innerHTML = "";
    debugbox.style.display = "none";
}

function correctionTreeRequest(method, path, fields) {
    var form = document.createElement("form");

    form.method = method;
    form.action = path;
    Object.keys(fields || {}).forEach(function (name) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    silent_submitf(form, {
        tofill: "correction-catalog",
        after_success: function () {
            form.remove();
            correctionClearStaleDebug();
            correctionRestoreActiveTab();
        }
    });
    return false;
}

function correctionTreePromptDirectory(parentId) {
    var name = prompt("Nom du nouveau dossier :");

    if (name === null || name.trim() === "")
        return false;
    return correctionTreeRequest("post", "/api/correction", {
        action: "category",
        name: name.trim(),
        id_parent: parentId > 0 ? parentId : ""
    });
}

function correctionTreePromptFile(categoryId) {
    var filename = prompt("Nom du nouveau fichier Dabsic :", "nouveau.dab");

    if (filename === null || filename.trim() === "")
        return false;
    if (!/\.dab$/i.test(filename))
        filename += ".dab";
    return correctionTreeRequest("post", "/api/correction", {
        action: "create_file",
        id_category: categoryId,
        filename: filename.trim()
    });
}

function correctionTreeOpenDabsic(relativePath) {
    if (!relativePath)
        return false;
    window.open(
        "index.php?p=DabsicEditorMenu&file=" + encodeURIComponent("dres/corrections/" + relativePath),
        "_blank",
        "noopener"
    );
    return false;
}


function correctionTreeAllowedUploadName(name) {
    name = String(name || "").toLowerCase();
    return name !== ".htaccess" && name !== ".user.ini";
}

function correctionTreeUploadFiles(categoryId, files) {
    var accepted = [];
    var transfer = new DataTransfer();
    var form;
    var input;

    if (categoryId <= 0 || !files || !files.length)
        return false;
    Array.prototype.forEach.call(files, function (file) {
        if (correctionTreeAllowedUploadName(file.name)) {
            accepted.push(file);
            transfer.items.add(file);
        }
    });
    if (!accepted.length) {
        alert("Aucun fichier importable n’a été trouvé.");
        return false;
    }

    form = document.createElement("form");
    form.method = "post";
    form.action = "/api/correction";
    form.enctype = "multipart/form-data";
    form.setAttribute("data-direct-file-upload", "1");
    form.innerHTML =
        '<input type="hidden" name="action" value="upload" />' +
        '<input type="hidden" name="id_category" value="' + categoryId + '" />';
    input = document.createElement("input");
    input.type = "file";
    input.name = "file[]";
    input.multiple = true;
    input.files = transfer.files;
    form.appendChild(input);
    document.body.appendChild(form);

    silent_submitf(form, {
        tofill: "correction-catalog",
        after_success: function () {
            form.remove();
            correctionRestoreActiveTab();
        }
    });
    return false;
}

function correctionTreeUploadEntries(categoryId, files) {
    var form = new FormData();
    var accepted = 0;

    form.append("action", "upload_tree");
    form.append("id_category", categoryId);
    files.forEach(function (item) {
        if (!correctionTreeAllowedUploadName(item.file.name))
            return;
        form.append("file[]", item.file, item.file.name);
        form.append("relative_path[]", item.path || item.file.name);
        accepted++;
    });
    if (!accepted) {
        alert("Le dossier ne contient aucun fichier importable.");
        return false;
    }
    fetch("/api/correction", {method: "POST", body: form, credentials: "same-origin"})
        .then(function (response) { return response.json(); })
        .then(function (packet) {
            if (packet.result !== "ok")
                throw new Error(packet.msg || "Échec de l’import");
            document.getElementById("correction-catalog").innerHTML = packet.content;
            correctionRestoreActiveTab();
        })
        .catch(function (error) { alert(error.message); });
    return false;
}

function correctionTreeChooseFolder(categoryId) {
    var input = document.getElementById("correction-folder-input");
    if (!input)
        return false;
    input.dataset.categoryId = categoryId;
    input.click();
    return false;
}

function correctionTreeUploadInputFolder(input) {
    var files = Array.prototype.map.call(input.files || [], function (file) {
        return {file: file, path: file.webkitRelativePath || file.name};
    });
    var categoryId = parseInt(input.dataset.categoryId || "1", 10) || 1;
    correctionTreeUploadEntries(categoryId, files);
    input.value = "";
    return false;
}

function correctionTreeReadEntry(entry, prefix, output) {
    return new Promise(function (resolve) {
        if (entry.isFile) {
            entry.file(function (file) {
                output.push({file: file, path: prefix + file.name});
                resolve();
            }, resolve);
            return;
        }
        if (!entry.isDirectory) {
            resolve();
            return;
        }
        var reader = entry.createReader();
        var children = [];
        function readBatch() {
            reader.readEntries(function (batch) {
                if (!batch.length) {
                    Promise.all(children.map(function (child) {
                        return correctionTreeReadEntry(child, prefix + entry.name + "/", output);
                    })).then(resolve);
                    return;
                }
                children = children.concat(batch);
                readBatch();
            }, resolve);
        }
        readBatch();
    });
}

function correctionTreeUploadDrop(categoryId, transfer) {
    var items = Array.prototype.slice.call(transfer.items || []);
    var entries = items.map(function (item) {
        return item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
    }).filter(Boolean);
    var output = [];

    if (!entries.length)
        return correctionTreeUploadFiles(categoryId, transfer.files);
    Promise.all(entries.map(function (entry) {
        return correctionTreeReadEntry(entry, "", output);
    })).then(function () {
        correctionTreeUploadEntries(categoryId, output);
    });
    return false;
}

function correctionTreeDownloadSelection() {
    var checked = document.querySelectorAll("#correction-catalog .correction-tree-select:checked");
    var form;
    if (!checked.length) {
        alert("Sélectionnez au moins un fichier ou un dossier.");
        return false;
    }
    form = document.createElement("form");
    form.method = "post";
    form.action = "/api/correction";
    form.target = "_blank";
    form.innerHTML = '<input type="hidden" name="action" value="download_selection" />';
    checked.forEach(function (box) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = box.name;
        input.value = box.value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
    return false;
}

function correctionTreeDeleteAsset(id, name) {
    if (!confirm("Supprimer le fichier « " + name + " » ?"))
        return false;
    return correctionTreeRequest("delete", "/api/correction/" + id, {action: "asset"});
}

function correctionTreeDeleteCategory(id, name) {
    if (!confirm("Supprimer le dossier « " + name + " », tous ses sous-dossiers et tous les fichiers qu’il contient ?"))
        return false;
    return correctionTreeRequest("delete", "/api/correction/" + id, {action: "category"});
}

function correctionTreeExpandAll(opened) {
    document.querySelectorAll("#correction-catalog .correction-tree details").forEach(function (details) {
        details.open = opened;
    });
}

(function correctionInstallTreeDnD() {
    var dragged = null;

    function correctionDragContainsFiles(event) {
        var transfer = event.dataTransfer;
        var types;

        if (!transfer)
            return false;
        if (transfer.files && transfer.files.length > 0)
            return true;
        types = transfer.types;
        if (!types)
            return false;
        if (typeof types.contains === "function" && types.contains("Files"))
            return true;
        return Array.prototype.indexOf.call(types, "Files") !== -1;
    }

    document.addEventListener("dragstart", function (event) {
        var node = event.target.closest && event.target.closest(".correction-tree-node[draggable='true']");
        if (!node || event.target.closest("button, select, input"))
            return;
        dragged = {
            type: node.getAttribute("data-correction-node-type"),
            id: node.getAttribute("data-correction-node-id")
        };
        node.classList.add("dragging");
        event.dataTransfer.effectAllowed = "copyMove";
        event.dataTransfer.setData("text/plain", dragged.type + ":" + dragged.id);
        if (dragged.type === "asset" && node.dataset.correctionDownloadUrl) {
            event.dataTransfer.setData(
                "DownloadURL",
                "application/octet-stream:" + node.dataset.correctionDownloadName + ":" + new URL(node.dataset.correctionDownloadUrl, location.href).href
            );
        }
    });
    document.addEventListener("dragend", function (event) {
        var node = event.target.closest && event.target.closest(".correction-tree-node");
        if (node)
            node.classList.remove("dragging");
        document.querySelectorAll(".correction-tree-row.drag-over, .correction-tree-root.drag-over").forEach(function (item) {
            item.classList.remove("drag-over");
        });
        dragged = null;
    });
    document.addEventListener("dragenter", function (event) {
        if (correctionDragContainsFiles(event) && event.target.closest && event.target.closest("#correction-catalog"))
            event.preventDefault();
    });
    document.addEventListener("dragover", function (event) {
        var target = event.target.closest && event.target.closest("[data-correction-drop-category]");
        var externalFiles = correctionDragContainsFiles(event);
        var targetId;

        if (!target || (!dragged && !externalFiles))
            return;
        targetId = parseInt(target.getAttribute("data-correction-drop-category"), 10) || 0;
        if ((dragged && dragged.type === "asset" && targetId === 0) || (externalFiles && targetId === 0))
            return;
        event.preventDefault();
        event.dataTransfer.dropEffect = externalFiles ? "copy" : "move";
        target.classList.add("drag-over");
    });
    document.addEventListener("dragleave", function (event) {
        var target = event.target.closest && event.target.closest("[data-correction-drop-category]");
        if (target && !target.contains(event.relatedTarget))
            target.classList.remove("drag-over");
    });
    document.addEventListener("drop", function (event) {
        var target = event.target.closest && event.target.closest("[data-correction-drop-category]");
        var externalFiles = correctionDragContainsFiles(event);
        var inCatalog = event.target.closest && event.target.closest("#correction-catalog");
        var targetId;

        // Never let the browser navigate to a file dropped on the Corrections page.
        if (externalFiles && inCatalog)
            event.preventDefault();
        if (!target || (!dragged && !externalFiles))
            return;
        targetId = parseInt(target.getAttribute("data-correction-drop-category"), 10) || 0;
        target.classList.remove("drag-over");
        if ((dragged && dragged.type === "asset" && targetId === 0) || (externalFiles && targetId === 0))
            return;
        event.preventDefault();
        if (externalFiles) {
            correctionTreeUploadDrop(targetId, event.dataTransfer);
            return;
        }
        correctionTreeRequest("put", "/api/correction/" + dragged.id, {
            action: "move_node",
            node_type: dragged.type,
            target_category: targetId
        });
    });
})();

function correctionConfirmSync(form) {
    if (!confirm("Synchroniser l’intégralité du catalogue Infosphere vers Distrans ? Les fichiers distants absents d’Infosphere seront retirés de la génération active."))
        return false;
    return silent_submit(form, 'correction-catalog');
}
function correctionConfirmRollback(form) {
    if (!confirm("Restaurer la génération de corrections précédemment active sur Distrans ?"))
        return false;
    return silent_submit(form, 'correction-catalog');
}
</script>

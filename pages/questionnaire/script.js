(function () {
    "use strict";

    var I18N = window.QuestionnaireBuilderI18n || {};

    function t(key, fallback) {
        return Object.prototype.hasOwnProperty.call(I18N, key) ? I18N[key] : fallback;
    }

    function q(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function qa(root, selector) {
        return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
    }

    function node(tag, className, text) {
        var out = document.createElement(tag);
        if (className)
            out.className = className;
        if (text !== undefined && text !== null)
            out.textContent = String(text);
        return out;
    }

    function button(label, className, action, title) {
        var out = node("button", className || "questionnaire-small", label);
        out.type = "button";
        if (action)
            out.setAttribute("data-q-action", action);
        if (title)
            out.title = title;
        return out;
    }

    function input(type, value, className) {
        var out = node("input", className || "");
        out.type = type || "text";
        if (type === "checkbox" || type === "radio")
            out.checked = !!value;
        else
            out.value = value === undefined || value === null ? "" : String(value);
        return out;
    }

    function textarea(value, rows) {
        var out = node("textarea");
        out.rows = rows || 2;
        out.value = value === undefined || value === null ? "" : String(value);
        return out;
    }

    function field(labelText, control, className, help) {
        var label = node("label", "questionnaire-field" + (className ? " " + className : ""));
        label.appendChild(node("span", "", labelText));
        label.appendChild(control);
        if (help)
            label.appendChild(node("small", "", help));
        return label;
    }

    function lines(value) {
        if (Array.isArray(value))
            return value.join("\n");
        return String(value || "");
    }

    function readLines(value) {
        var seen = Object.create(null);
        return String(value || "").split(/\r?\n/).map(function (entry) {
            return entry.trim();
        }).filter(function (entry) {
            if (!entry || seen[entry])
                return false;
            seen[entry] = true;
            return true;
        });
    }

    function symbol(value, fallback) {
        value = String(value || "").trim().replace(/[^A-Za-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
        if (!/^[A-Za-z_]/.test(value))
            value = fallback || "Item";
        return value || fallback || "Item";
    }

    function numeric(value, fallback) {
        var out = parseFloat(value);
        return Number.isFinite(out) ? out : fallback;
    }

    function move(element, direction) {
        var sibling;
        if (!element || !element.parentNode)
            return;
        if (direction < 0) {
            sibling = element.previousElementSibling;
            if (sibling)
                element.parentNode.insertBefore(element, sibling);
        } else {
            sibling = element.nextElementSibling;
            if (sibling)
                element.parentNode.insertBefore(sibling, element);
        }
    }

    function actionBar(kind) {
        var actions = node("div", "questionnaire-card-actions");
        actions.appendChild(button("↑", "questionnaire-icon", "up", t("QuestionnaireBuilderMoveUp", "Monter")));
        actions.appendChild(button("↓", "questionnaire-icon", "down", t("QuestionnaireBuilderMoveDown", "Descendre")));
        actions.appendChild(button("×", "questionnaire-icon questionnaire-danger", "delete", t("QuestionnaireBuilderDelete", "Supprimer") + " " + kind));
        return actions;
    }

    function choiceRow(value, label, correct, radioName) {
        var row = node("div", "questionnaire-choice-row");
        var selector = input(radioName ? "radio" : "checkbox", correct, "questionnaire-choice-correct");
        var stable = input("text", value, "questionnaire-choice-stable-value");
        var text = input("text", label, "questionnaire-choice-value");
        if (radioName)
            selector.name = radioName;
        selector.title = t("QuestionnaireBuilderCorrect", "Bonne réponse");
        stable.placeholder = t("QuestionnaireBuilderChoiceValue", "Valeur stable");
        stable.title = t("QuestionnaireBuilderChoiceValueHelp", "Identifiant stocké, indépendant du libellé affiché");
        text.placeholder = t("QuestionnaireBuilderChoice", "Proposition");
        row.appendChild(selector);
        row.appendChild(stable);
        row.appendChild(text);
        row.appendChild(button("↑", "questionnaire-icon", "choice-up", t("QuestionnaireBuilderMoveUp", "Monter")));
        row.appendChild(button("↓", "questionnaire-icon", "choice-down", t("QuestionnaireBuilderMoveDown", "Descendre")));
        row.appendChild(button("×", "questionnaire-icon questionnaire-danger", "choice-delete", t("QuestionnaireBuilderDelete", "Supprimer")));
        return row;
    }

    function nextChoiceStableValue(card) {
        var used = Object.create(null);
        qa(card, ".questionnaire-choice-stable-value").forEach(function (inputNode) {
            used[String(inputNode.value || "").trim()] = true;
        });
        var index = 1;
        while (used["choice_" + index])
            ++index;
        return "choice_" + index;
    }

    function refreshQuestionMode(card) {
        var type = q(card, "[data-q-question-type]").value;
        var choices = q(card, "[data-q-choices-block]");
        var freeCorrect = q(card, "[data-q-free-correct-block]");
        var policy = q(card, "[data-q-question-policy]").value;
        var penalty = q(card, "[data-q-penalty-field]");
        var showChoices = type === "radio" || type === "checkbox" || type === "scale";
        var rows = qa(card, ".questionnaire-choice-row");
        var radioName = "q-correct-" + (card.getAttribute("data-q-uid") || Math.random().toString(36).slice(2));

        choices.hidden = !showChoices;
        if (freeCorrect)
            freeCorrect.hidden = showChoices;
        if (type === "scale" && rows.length < 2) {
            var list = q(card, "[data-q-choices]");
            ["1", "2", "3", "4", "5"].forEach(function (value) {
                list.appendChild(choiceRow(value, value, false, ""));
            });
            rows = qa(card, ".questionnaire-choice-row");
        }
        penalty.hidden = policy !== "penalty";
        rows.forEach(function (row) {
            var current = q(row, ".questionnaire-choice-correct");
            if (!current)
                return;
            if ((type === "radio" && current.type !== "radio") || (type === "checkbox" && current.type !== "checkbox")) {
                var replacement = input(type === "radio" ? "radio" : "checkbox", current.checked, "questionnaire-choice-correct");
                replacement.title = current.title;
                current.replaceWith(replacement);
                current = replacement;
            }
            current.name = type === "radio" ? radioName : "";
            current.style.display = type === "scale" ? "none" : "";
        });
        if (type === "radio") {
            var selected = rows.filter(function (row) {
                var check = q(row, ".questionnaire-choice-correct");
                return check && check.checked;
            });
            selected.slice(1).forEach(function (row) {
                q(row, ".questionnaire-choice-correct").checked = false;
            });
        }
    }

    function questionCard(question, index) {
        var card = node("article", "questionnaire-question-card");
        var uid = Math.random().toString(36).slice(2);
        var head = node("div", "questionnaire-card-heading");
        var title = node("div", "questionnaire-card-title");
        var grid = node("div", "questionnaire-form-grid");
        var type = node("select");
        var policy = node("select");
        var choicesBlock = node("div", "questionnaire-choices-block");
        var choicesList = node("div", "questionnaire-choices-list");
        var freeCorrectBlock = node("div", "questionnaire-free-correct-block");
        var freeCorrect = textarea(lines(question.correct), 2);
        var required = input("checkbox", !!question.required);
        var points = input("number", question.points === undefined ? 1 : question.points);
        var penalty = input("number", question.penalty === undefined ? 1 : question.penalty);
        var medals = textarea(lines(question.medals), 2);
        var correct = Array.isArray(question.correct) ? question.correct : [];

        card.setAttribute("data-q-question", "1");
        card.setAttribute("data-q-uid", uid);
        title.appendChild(node("strong", "", t("QuestionnaireBuilderQuestion", "Question")));
        title.appendChild(node("code", "questionnaire-question-key-display", question.key || ("Question" + (index + 1))));
        head.appendChild(title);
        head.appendChild(actionBar(t("QuestionnaireBuilderQuestionKind", "la question")));
        card.appendChild(head);

        var keyInput = input("text", question.key || ("Question" + (index + 1)));
        keyInput.setAttribute("data-q-question-key", "1");
        var labelInput = input("text", question.label || "Nouvelle question");
        labelInput.setAttribute("data-q-question-label", "1");
        grid.appendChild(field(t("QuestionnaireBuilderDabsicId", "Identifiant Dabsic"), keyInput));
        grid.appendChild(field(t("QuestionnaireBuilderLabel", "Intitulé"), labelInput, "questionnaire-field-wide"));

        [
            ["text", t("QuestionnaireBuilderTypeText", "Réponse libre courte")],
            ["textarea", t("QuestionnaireBuilderTypeTextarea", "Réponse libre longue")],
            ["radio", t("QuestionnaireBuilderTypeRadio", "Choix unique")],
            ["checkbox", t("QuestionnaireBuilderTypeCheckbox", "Choix multiples")],
            ["scale", t("QuestionnaireBuilderTypeScale", "Échelle")]
        ].forEach(function (entry) {
            var option = node("option", "", entry[1]);
            option.value = entry[0];
            option.selected = (question.type || "text") === entry[0];
            type.appendChild(option);
        });
        type.setAttribute("data-q-question-type", "1");
        grid.appendChild(field(t("QuestionnaireBuilderType", "Type"), type));

        required.setAttribute("data-q-question-required", "1");
        grid.appendChild(field(t("QuestionnaireBuilderRequired", "Obligatoire"), required));
        points.min = "0";
        points.step = "0.01";
        points.setAttribute("data-q-question-points", "1");
        grid.appendChild(field(t("QuestionnaireBuilderPoints", "Points"), points));

        [["exact", t("QuestionnaireBuilderPolicyExact", "Question fausse = 0")], ["penalty", t("QuestionnaireBuilderPolicyPenalty", "Pénalité par erreur")]].forEach(function (entry) {
            var option = node("option", "", entry[1]);
            option.value = entry[0];
            option.selected = (question.policy || "exact") === entry[0];
            policy.appendChild(option);
        });
        policy.setAttribute("data-q-question-policy", "1");
        grid.appendChild(field(t("QuestionnaireBuilderErrorPolicy", "Politique d’erreur"), policy));

        penalty.min = "0";
        penalty.step = "0.01";
        penalty.setAttribute("data-q-question-penalty", "1");
        var penaltyField = field(t("QuestionnaireBuilderPenalty", "Pénalité"), penalty);
        penaltyField.setAttribute("data-q-penalty-field", "1");
        grid.appendChild(penaltyField);

        medals.setAttribute("data-q-question-medals", "1");
        grid.appendChild(field(t("QuestionnaireBuilderQuestionMedals", "Médailles si la question est correcte"), medals, "questionnaire-field-wide", t("QuestionnaireBuilderOneMedal", "Une médaille par ligne.")));
        card.appendChild(grid);

        choicesBlock.setAttribute("data-q-choices-block", "1");
        choicesBlock.appendChild(node("h4", "", t("QuestionnaireBuilderChoices", "Propositions et bonnes réponses")));
        choicesBlock.appendChild(node("p", "questionnaire-help", t("QuestionnaireBuilderChoicesHelp", "La coche à gauche marque une bonne réponse. Un choix unique ne peut avoir qu’une seule bonne réponse.")));
        choicesList.setAttribute("data-q-choices", "1");
        (question.choices || []).forEach(function (choice, choiceIndex) {
            var stableValue = (question.choice_values || [])[choiceIndex] || choice;
            choicesList.appendChild(choiceRow(stableValue, choice, correct.indexOf(stableValue) !== -1, (question.type || "text") === "radio" ? "q-correct-" + uid : ""));
        });
        choicesBlock.appendChild(choicesList);
        var addChoice = button("+ " + t("QuestionnaireBuilderAddChoice", "Ajouter une proposition"), "questionnaire-secondary questionnaire-small", "add-choice");
        choicesBlock.appendChild(addChoice);
        card.appendChild(choicesBlock);

        freeCorrectBlock.setAttribute("data-q-free-correct-block", "1");
        freeCorrect.setAttribute("data-q-correct-free", "1");
        freeCorrectBlock.appendChild(field(t("QuestionnaireBuilderExpected", "Réponses attendues"), freeCorrect, "questionnaire-field-wide", t("QuestionnaireBuilderExpectedHelp", "Une réponse acceptée par ligne. Laissez vide pour un champ libre non corrigé automatiquement.")));
        card.appendChild(freeCorrectBlock);

        type.addEventListener("change", function () { refreshQuestionMode(card); });
        policy.addEventListener("change", function () { refreshQuestionMode(card); });
        keyInput.addEventListener("input", function () {
            q(card, ".questionnaire-question-key-display").textContent = keyInput.value || "?";
        });
        refreshQuestionMode(card);
        return card;
    }

    function groupCard(group, index) {
        var card = node("section", "questionnaire-group-card");
        var head = node("div", "questionnaire-card-heading questionnaire-group-heading");
        var title = node("div", "questionnaire-card-title");
        var grid = node("div", "questionnaire-form-grid");
        var questions = node("div", "questionnaire-questions");
        var key = input("text", group.key || ("Group" + (index + 1)));
        var label = input("text", group.label || t("QuestionnaireBuilderNewGroup", "Nouveau groupe"));
        var percent = input("number", group.minimum_percent || 0);
        var medals = textarea(lines(group.medals), 2);

        card.setAttribute("data-q-group", "1");
        title.appendChild(node("h3", "", group.label || t("QuestionnaireBuilderNewGroup", "Nouveau groupe")));
        title.appendChild(node("code", "questionnaire-group-key-display", group.key || ("Group" + (index + 1))));
        head.appendChild(title);
        head.appendChild(actionBar(t("QuestionnaireBuilderGroupKind", "le groupe")));
        card.appendChild(head);

        key.setAttribute("data-q-group-key", "1");
        label.setAttribute("data-q-group-label", "1");
        percent.min = "0";
        percent.max = "100";
        percent.step = "0.01";
        percent.setAttribute("data-q-group-percent", "1");
        medals.setAttribute("data-q-group-medals", "1");
        grid.appendChild(field(t("QuestionnaireBuilderDabsicId", "Identifiant Dabsic"), key));
        grid.appendChild(field(t("QuestionnaireBuilderGroupName", "Nom du groupe"), label, "questionnaire-field-wide"));
        grid.appendChild(field(t("QuestionnaireBuilderGroupThreshold", "Seuil du groupe (%)"), percent));
        grid.appendChild(field(t("QuestionnaireBuilderGroupMedals", "Médailles du groupe"), medals, "questionnaire-field-wide", t("QuestionnaireBuilderGroupMedalsHelp", "Une médaille par ligne ; elles seront associées à l’atteinte du seuil du groupe.")));
        card.appendChild(grid);

        questions.setAttribute("data-q-questions", "1");
        (group.questions || []).forEach(function (question, questionIndex) {
            questions.appendChild(questionCard(question, questionIndex));
        });
        card.appendChild(questions);
        card.appendChild(button("+ " + t("QuestionnaireBuilderAddQuestion", "Ajouter une question"), "questionnaire-secondary", "add-question"));

        key.addEventListener("input", function () {
            q(card, ".questionnaire-group-key-display").textContent = key.value || "?";
        });
        label.addEventListener("input", function () {
            q(card, ".questionnaire-card-title h3").textContent = label.value || t("QuestionnaireBuilderNewGroup", "Nouveau groupe");
        });
        return card;
    }

    function serializeBuilder(builder, original) {
        var model = {
            format_version: 2,
            codename: original.codename || "questionnaire",
            name: q(builder, '[data-q-general="name"]').value.trim(),
            description: q(builder, '[data-q-general="description"]').value,
            minimum_percent: numeric(q(builder, '[data-q-general="minimum_percent"]').value, 0),
            medals: readLines(q(builder, '[data-q-general="medals"]').value),
            groups: []
        };

        qa(builder, "[data-q-group]").forEach(function (groupCardNode, groupIndex) {
            var group = {
                key: symbol(q(groupCardNode, "[data-q-group-key]").value, "Group" + (groupIndex + 1)),
                label: q(groupCardNode, "[data-q-group-label]").value.trim() || (t("QuestionnaireBuilderGroup", "Groupe") + " " + (groupIndex + 1)),
                minimum_percent: numeric(q(groupCardNode, "[data-q-group-percent]").value, 0),
                medals: readLines(q(groupCardNode, "[data-q-group-medals]").value),
                questions: []
            };
            qa(groupCardNode, "[data-q-question]").forEach(function (questionCardNode, questionIndex) {
                var type = q(questionCardNode, "[data-q-question-type]").value;
                var choices = [];
                var choiceValues = [];
                var correct = [];
                if (type === "radio" || type === "checkbox" || type === "scale") {
                    qa(questionCardNode, ".questionnaire-choice-row").forEach(function (row) {
                        var value = q(row, ".questionnaire-choice-stable-value").value.trim();
                        var label = q(row, ".questionnaire-choice-value").value.trim();
                        var selected = q(row, ".questionnaire-choice-correct").checked;
                        if (!value || !label)
                            return;
                        choices.push(label);
                        choiceValues.push(value);
                        if (selected && type !== "scale")
                            correct.push(value);
                    });
                } else {
                    correct = readLines(q(questionCardNode, "[data-q-correct-free]") ? q(questionCardNode, "[data-q-correct-free]").value : "");
                }
                group.questions.push({
                    key: symbol(q(questionCardNode, "[data-q-question-key]").value, "Question" + (questionIndex + 1)),
                    label: q(questionCardNode, "[data-q-question-label]").value.trim() || ("Question " + (questionIndex + 1)),
                    type: type,
                    required: q(questionCardNode, "[data-q-question-required]").checked,
                    points: numeric(q(questionCardNode, "[data-q-question-points]").value, 1),
                    policy: q(questionCardNode, "[data-q-question-policy]").value,
                    penalty: numeric(q(questionCardNode, "[data-q-question-penalty]").value, 1),
                    choices: choices,
                    choice_values: choiceValues,
                    correct: correct,
                    medals: readLines(q(questionCardNode, "[data-q-question-medals]").value)
                });
            });
            model.groups.push(group);
        });
        return model;
    }

    function validateChoiceRows(builder) {
        var error = "";
        qa(builder, "[data-q-question]").some(function (card) {
            var type = q(card, "[data-q-question-type]").value;
            var rows;
            var stableSeen = Object.create(null);
            var labelSeen = Object.create(null);
            if (["radio", "checkbox", "scale"].indexOf(type) === -1)
                return false;
            rows = qa(card, ".questionnaire-choice-row");
            if ((type === "radio" || type === "checkbox") && !rows.length) {
                error = t("QuestionnaireBuilderMissingChoice", "Une question à choix doit contenir au moins une proposition.");
                return true;
            }
            return rows.some(function (row) {
                var value = q(row, ".questionnaire-choice-stable-value").value.trim();
                var label = q(row, ".questionnaire-choice-value").value.trim();
                if (!value || !label) {
                    error = t("QuestionnaireBuilderIncompleteChoice", "Chaque proposition doit avoir une valeur stable et un libellé.");
                    return true;
                }
                if (stableSeen[value]) {
                    error = t("QuestionnaireBuilderDuplicateChoiceValue", "Deux propositions utilisent la même valeur stable : ") + value;
                    return true;
                }
                if (labelSeen[label]) {
                    error = t("QuestionnaireBuilderDuplicateChoiceLabel", "Deux propositions utilisent le même libellé : ") + label;
                    return true;
                }
                if (type === "scale" && !Number.isFinite(Number(value))) {
                    error = t("QuestionnaireBuilderScaleNumericValue", "Les valeurs stables d’une échelle doivent être numériques.");
                    return true;
                }
                stableSeen[value] = true;
                labelSeen[label] = true;
                return false;
            });
        });
        return error;
    }

    function attachBuilder(builder) {
        var modelNode = q(builder, "[data-questionnaire-model]");
        var groups = q(builder, "[data-questionnaire-groups]");
        var readonly = builder.getAttribute("data-readonly") === "1";
        var model;
        if (!modelNode || !groups)
            return;
        try {
            model = JSON.parse(modelNode.textContent || "{}");
        } catch (error) {
            console.error("Invalid questionnaire model", error);
            return;
        }

        (model.groups || []).forEach(function (group, index) {
            groups.appendChild(groupCard(group, index));
        });

        if (readonly) {
            qa(builder, "input, textarea, select, button").forEach(function (control) {
                control.disabled = true;
            });
            return;
        }

        builder.addEventListener("click", function (event) {
            var target = event.target.closest ? event.target.closest("[data-q-action]") : null;
            var action;
            var card;
            if (!target)
                return;
            action = target.getAttribute("data-q-action");
            if (action === "up" || action === "down") {
                card = target.closest("[data-q-question], [data-q-group]");
                move(card, action === "up" ? -1 : 1);
            } else if (action === "delete") {
                card = target.closest("[data-q-question], [data-q-group]");
                if (card && window.confirm(t("QuestionnaireBuilderDeleteConfirm", "Supprimer cet élément ?")))
                    card.remove();
            } else if (action === "add-question") {
                card = target.closest("[data-q-group]");
                q(card, "[data-q-questions]").appendChild(questionCard({
                    key: "Question" + (qa(builder, "[data-q-question]").length + 1),
                    label: t("QuestionnaireBuilderNewQuestion", "Nouvelle question"),
                    type: "radio",
                    required: true,
                    points: 1,
                    policy: "exact",
                    penalty: 1,
                    choices: [t("QuestionnaireBuilderChoice", "Proposition") + " 1", t("QuestionnaireBuilderChoice", "Proposition") + " 2"],
                    choice_values: ["choice_1", "choice_2"],
                    correct: [],
                    medals: []
                }, qa(card, "[data-q-question]").length));
            } else if (action === "add-choice") {
                card = target.closest("[data-q-question]");
                var type = q(card, "[data-q-question-type]").value;
                var choiceIndex = qa(card, ".questionnaire-choice-row").length + 1;
                q(card, "[data-q-choices]").appendChild(choiceRow(nextChoiceStableValue(card), t("QuestionnaireBuilderChoice", "Proposition") + " " + choiceIndex, false, type === "radio" ? "q-correct-" + card.getAttribute("data-q-uid") : ""));
                refreshQuestionMode(card);
            } else if (action === "choice-up" || action === "choice-down") {
                move(target.closest(".questionnaire-choice-row"), action === "choice-up" ? -1 : 1);
            } else if (action === "choice-delete") {
                target.closest(".questionnaire-choice-row").remove();
            }
        });

        var addGroup = q(builder, "[data-q-add-group]");
        if (addGroup)
            addGroup.addEventListener("click", function () {
                groups.appendChild(groupCard({
                    key: "Group" + (qa(builder, "[data-q-group]").length + 1),
                    label: t("QuestionnaireBuilderNewGroup", "Nouveau groupe"),
                    minimum_percent: 0,
                    medals: [],
                    questions: []
                }, qa(builder, "[data-q-group]").length));
            });

        builder.addEventListener("submit", function (event) {
            var out = q(builder, "[data-questionnaire-model-output]");
            var choiceError = validateChoiceRows(builder);
            var serialized;
            if (choiceError) {
                event.preventDefault();
                window.alert(choiceError);
                return;
            }
            serialized = serializeBuilder(builder, model);
            if (!serialized.name) {
                event.preventDefault();
                window.alert(t("QuestionnaireBuilderNameRequired", "Le questionnaire doit avoir un nom."));
                return;
            }
            out.value = JSON.stringify(serialized);
        });
    }

    function init() {
        qa(document, "[data-questionnaire-builder]").forEach(function (builder) {
            if (builder.getAttribute("data-questionnaire-attached") === "1")
                return;
            builder.setAttribute("data-questionnaire-attached", "1");
            attachBuilder(builder);
        });
    }

    if (document.readyState === "loading")
        document.addEventListener("DOMContentLoaded", init);
    else
        init();

    // The embedded source editor is authoritative.  After a successful source
    // save, reload so the visual editor and preview are rebuilt from mergeconf.
    document.addEventListener("dabsic-editor-saved", function (event) {
        if (event.target && event.target.closest && event.target.closest(".questionnaire-source-panel"))
            window.setTimeout(function () { window.location.reload(); }, 250);
    });
}());

/* Resource tree ---------------------------------------------------------- */
function questionnaireTreeText(key, fallback) {
    var dict = window.QuestionnaireTreeI18n || {};
    return dict[key] || fallback;
}

function questionnaireTreePanel() {
    return document.querySelector('.questionnaire-tree-panel');
}

function questionnaireTreeReplace(packet) {
    var panel = questionnaireTreePanel();
    var holder;

    if (!panel)
        return;
    holder = document.createElement('div');
    holder.innerHTML = packet.content || '';
    if (holder.firstElementChild)
        panel.replaceWith(holder.firstElementChild);
}

function questionnaireTreeRequest(method, schoolId, action, fields) {
    var body = new URLSearchParams();

    Object.keys(fields || {}).forEach(function (name) {
        body.append(name, fields[name]);
    });
    fetch('/api/questionnaire/' + encodeURIComponent(schoolId) + '/' + action, {
        method: method.toUpperCase(),
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
    })
        .then(function (response) { return response.json(); })
        .then(function (packet) {
            if (packet.result !== 'ok')
                throw new Error(packet.msg || questionnaireTreeText('error', 'Échec de l’opération.'));
            questionnaireTreeReplace(packet);
        })
        .catch(function (error) { alert(error.message); });
    return false;
}

function questionnaireTreeSwitchSchool(id) {
    var url = new URL(window.location.href);
    url.searchParams.set('p', 'QuestionnaireMenu');
    url.searchParams.delete('a');
    url.searchParams.set('quiz_school', id);
    window.location.href = url.toString();
    return false;
}

function questionnaireTreePromptDirectory(schoolId, parent) {
    var name = prompt(questionnaireTreeText('directoryPrompt', 'Nom du nouveau dossier :'));
    if (name === null || name.trim() === '')
        return false;
    return questionnaireTreeRequest('POST', schoolId, 'tree_directory', {
        parent: parent || '',
        name: name.trim()
    });
}

function questionnaireTreePromptFile(schoolId, directory) {
    var filename = prompt(questionnaireTreeText('filePrompt', 'Nom du nouveau fichier Dabsic :'), 'nouveau.dab');
    if (filename === null || filename.trim() === '')
        return false;
    if (!/\.dab$/i.test(filename))
        filename += '.dab';
    return questionnaireTreeRequest('POST', schoolId, 'tree_file', {
        directory: directory || '',
        filename: filename.trim()
    });
}

function questionnaireTreeOpenDabsic(schoolId, relativePath) {
    var panel = questionnaireTreePanel();
    var root;
    if (!relativePath || !panel)
        return false;
    root = panel.querySelector('.questionnaire-tree-root-label strong');
    if (!root)
        return false;
    window.open(
        'index.php?p=DabsicEditorMenu&file=' + encodeURIComponent(root.textContent.trim() + '/' + relativePath),
        '_blank',
        'noopener'
    );
    return false;
}

function questionnaireTreeAllowedUploadName(name) {
    var lower = String(name || '').toLowerCase();
    var extension = lower.indexOf('.') === -1 ? '' : lower.split('.').pop();
    return lower !== '.htaccess' && lower !== '.user.ini' && lower !== 'index.php'
        && ['php', 'phtml', 'phar', 'cgi'].indexOf(extension) === -1;
}

function questionnaireTreeChooseFiles(schoolId, directory) {
    var input = document.getElementById('questionnaire-tree-files-input');
    if (!input)
        return false;
    input.dataset.schoolId = schoolId;
    input.dataset.directory = directory || '';
    input.click();
    return false;
}

function questionnaireTreeChooseFolder(schoolId, directory) {
    var input = document.getElementById('questionnaire-tree-folder-input');
    if (!input)
        return false;
    input.dataset.schoolId = schoolId;
    input.dataset.directory = directory || '';
    input.click();
    return false;
}

function questionnaireTreeUploadForm(schoolId, action, form) {
    return fetch('/api/questionnaire/' + encodeURIComponent(schoolId) + '/' + action, {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
    })
        .then(function (response) { return response.json(); })
        .then(function (packet) {
            if (packet.result !== 'ok')
                throw new Error(packet.msg || questionnaireTreeText('uploadError', 'Échec de l’import.'));
            questionnaireTreeReplace(packet);
        })
        .catch(function (error) { alert(error.message); });
}

function questionnaireTreeUploadFiles(schoolId, directory, files) {
    var form = new FormData();
    var accepted = 0;
    Array.prototype.forEach.call(files || [], function (file) {
        if (!questionnaireTreeAllowedUploadName(file.name))
            return;
        form.append('file[]', file, file.name);
        accepted++;
    });
    if (!accepted) {
        alert(questionnaireTreeText('nothingImportable', 'Aucun fichier importable n’a été trouvé.'));
        return false;
    }
    form.append('directory', directory || '');
    questionnaireTreeUploadForm(schoolId, 'tree_upload', form);
    return false;
}

function questionnaireTreeUploadFilesInput(input) {
    questionnaireTreeUploadFiles(
        parseInt(input.dataset.schoolId || '0', 10),
        input.dataset.directory || '',
        input.files || []
    );
    input.value = '';
    return false;
}

function questionnaireTreeUploadEntries(schoolId, directory, entries) {
    var form = new FormData();
    var accepted = 0;
    form.append('directory', directory || '');
    entries.forEach(function (item) {
        if (!questionnaireTreeAllowedUploadName(item.file.name))
            return;
        form.append('file[]', item.file, item.file.name);
        form.append('relative_path[]', item.path || item.file.name);
        accepted++;
    });
    if (!accepted) {
        alert(questionnaireTreeText('nothingImportableFolder', 'Le dossier ne contient aucun fichier importable.'));
        return false;
    }
    questionnaireTreeUploadForm(schoolId, 'tree_upload_folder', form);
    return false;
}

function questionnaireTreeUploadFolderInput(input) {
    var files = Array.prototype.map.call(input.files || [], function (file) {
        return {file: file, path: file.webkitRelativePath || file.name};
    });
    questionnaireTreeUploadEntries(
        parseInt(input.dataset.schoolId || '0', 10),
        input.dataset.directory || '',
        files
    );
    input.value = '';
    return false;
}

function questionnaireTreeReadEntry(entry, prefix, output) {
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
                        return questionnaireTreeReadEntry(child, prefix + entry.name + '/', output);
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

function questionnaireTreeUploadDrop(schoolId, directory, transfer) {
    var items = Array.prototype.slice.call(transfer.items || []);
    var entries = items.map(function (item) {
        return item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
    }).filter(Boolean);
    var output = [];

    if (!entries.length)
        return questionnaireTreeUploadFiles(schoolId, directory, transfer.files);
    Promise.all(entries.map(function (entry) {
        return questionnaireTreeReadEntry(entry, '', output);
    })).then(function () {
        questionnaireTreeUploadEntries(schoolId, directory, output);
    });
    return false;
}

function questionnaireTreeDownload(schoolId, paths) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '/api/questionnaire/' + encodeURIComponent(schoolId) + '/tree_download';
    form.target = '_blank';
    (paths || []).forEach(function (path) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'path[]';
        input.value = path;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
    return false;
}

function questionnaireTreeDownloadSelection(schoolId) {
    var checked = document.querySelectorAll('.questionnaire-tree-panel .questionnaire-tree-select:checked');
    if (!checked.length) {
        alert(questionnaireTreeText('selectSomething', 'Sélectionnez au moins un fichier ou un dossier.'));
        return false;
    }
    return questionnaireTreeDownload(schoolId, Array.prototype.map.call(checked, function (box) { return box.value; }));
}

function questionnaireTreeDelete(schoolId, path, directory) {
    var message = directory
        ? questionnaireTreeText('deleteDirectory', 'Supprimer ce dossier et toutes ses ressources ?')
        : questionnaireTreeText('deleteFile', 'Supprimer ce fichier ?');
    if (!confirm(message + '\n' + path))
        return false;
    return questionnaireTreeRequest('DELETE', schoolId, 'tree_delete', {path: path});
}

function questionnaireTreeExpandAll(opened) {
    document.querySelectorAll('.questionnaire-tree-panel .questionnaire-tree details').forEach(function (details) {
        details.open = opened;
    });
}

(function questionnaireInstallTreeDnD() {
    var dragged = null;

    function containsFiles(event) {
        var transfer = event.dataTransfer;
        var types;
        if (!transfer)
            return false;
        if (transfer.files && transfer.files.length > 0)
            return true;
        types = transfer.types;
        if (!types)
            return false;
        if (typeof types.contains === 'function' && types.contains('Files'))
            return true;
        return Array.prototype.indexOf.call(types, 'Files') !== -1;
    }

    document.addEventListener('dragstart', function (event) {
        var node = event.target.closest && event.target.closest('.questionnaire-tree-node[draggable="true"]');
        var panel;
        if (!node || event.target.closest('button,a,input,select'))
            return;
        panel = node.closest('.questionnaire-tree-panel');
        if (!panel)
            return;
        dragged = {
            school: parseInt(panel.dataset.questionnaireTreeSchool || '0', 10),
            path: node.dataset.questionnaireTreePath || ''
        };
        node.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.path);
    });

    document.addEventListener('dragend', function (event) {
        var node = event.target.closest && event.target.closest('.questionnaire-tree-node');
        if (node)
            node.classList.remove('dragging');
        document.querySelectorAll('.questionnaire-tree-row.drag-over, .questionnaire-tree-root.drag-over').forEach(function (item) {
            item.classList.remove('drag-over');
        });
        dragged = null;
    });

    document.addEventListener('dragover', function (event) {
        var target = event.target.closest && event.target.closest('[data-questionnaire-tree-drop]');
        var panel = event.target.closest && event.target.closest('.questionnaire-tree-panel');
        if (!target || !panel || (!dragged && !containsFiles(event)))
            return;
        event.preventDefault();
        event.dataTransfer.dropEffect = containsFiles(event) ? 'copy' : 'move';
        target.classList.add('drag-over');
    });

    document.addEventListener('dragleave', function (event) {
        var target = event.target.closest && event.target.closest('[data-questionnaire-tree-drop]');
        if (target && !target.contains(event.relatedTarget))
            target.classList.remove('drag-over');
    });

    document.addEventListener('drop', function (event) {
        var target = event.target.closest && event.target.closest('[data-questionnaire-tree-drop]');
        var panel = event.target.closest && event.target.closest('.questionnaire-tree-panel');
        var external = containsFiles(event);
        var schoolId;
        var directory;
        if (external && panel)
            event.preventDefault();
        if (!target || !panel || (!dragged && !external))
            return;
        event.preventDefault();
        target.classList.remove('drag-over');
        schoolId = parseInt(panel.dataset.questionnaireTreeSchool || '0', 10);
        directory = target.dataset.questionnaireTreeDrop || '';
        if (external) {
            questionnaireTreeUploadDrop(schoolId, directory, event.dataTransfer);
            return;
        }
        if (!dragged || dragged.school !== schoolId)
            return;
        questionnaireTreeRequest('PUT', schoolId, 'tree_move', {
            source: dragged.path,
            target: directory
        });
    });
})();

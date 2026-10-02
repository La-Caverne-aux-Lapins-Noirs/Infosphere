(function () {
    "use strict";

    var INDENT_WIDTH = 2;
    var CONTEXT_KINDS = [
        "function", "sequence", "csv", "text", "xml", "scope", "array",
        "disabled-square", "disabled-curly", "disabled-angle"
    ];
    var KEYWORDS = [
        "If", "Then", "EndIf", "ElseIf", "Else", "While", "EndWhile", "WEnd",
        "For", "To", "Step", "EndFor", "Next", "Do", "AgainIf", "Repeat", "Until",
        "Select", "Case", "EndSelect", "With", "EndWith", "Return", "Leave", "Break",
        "Brake", "Continue", "Link", "Goto", "Wait", "Print", "PrintErr", "Exec",
        "HaveValue", "NbrChildren", "NbrCase", "IsEmpty", "AddressOf", "Build", "Delete"
    ];
    var TYPES = ["integer", "int", "real", "string"];
    var CONSTANTS = ["NULL", "true", "false"];

    function trim(value) {
        return String(value).replace(/^[ \t\n\r]+|[ \t\n\r]+$/g, "");
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    function stripInlineComment(line) {
        var inString = false;
        var escaped = false;
        var i;
        var ch;

        for (i = 0; i < line.length; ++i) {
            ch = line.charAt(i);
            if (escaped) {
                escaped = false;
            } else if (inString && ch === "\\") {
                escaped = true;
            } else if (ch === "\"") {
                inString = !inString;
            } else if (!inString && ch === "'") {
                return line.substring(0, i);
            }
        }
        return line;
    }

    function codeLine(line) {
        return trim(stripInlineComment(line));
    }

    function blankOrComment(line) {
        return line === "" || line.charAt(0) === "'";
    }

    function entryKind(entry) {
        return entry && typeof entry === "object" ? entry.kind : entry;
    }

    function stackTop(stack) {
        return stack.length ? stack[stack.length - 1] : null;
    }

    function stackTopKind(stack) {
        return entryKind(stackTop(stack));
    }

    function contextKind(stack) {
        var i;
        var kind;

        for (i = stack.length - 1; i >= 0; --i) {
            kind = entryKind(stack[i]);
            if (CONTEXT_KINDS.indexOf(kind) !== -1)
                return kind;
        }
        return null;
    }

    function insideFunction(stack) {
        var i;
        for (i = stack.length - 1; i >= 0; --i)
            if (entryKind(stack[i]) === "function")
                return true;
        return false;
    }

    function popFunctionBlock(stack) {
        var out = stack.slice();
        var top = stackTopKind(out);
        if (top === "func-block" || top === "func-single")
            out.pop();
        return out;
    }

    function lineOpensDisabled(line) {
        if (/^\[!/.test(line))
            return "disabled-square";
        if (/^\{!/.test(line))
            return "disabled-curly";
        if (/^<!/.test(line))
            return "disabled-angle";
        return null;
    }

    function lineClosesDisabled(line, kind) {
        if (kind === "disabled-square")
            return /\]/.test(line);
        if (kind === "disabled-curly")
            return /}/.test(line);
        if (kind === "disabled-angle")
            return />/.test(line);
        return false;
    }

    function unquoteToken(value) {
        var first;
        var last;
        if (value.length < 2)
            return value;
        first = value.charAt(0);
        last = value.charAt(value.length - 1);
        if ((first === "\"" && last === "\"") || (first === "'" && last === "'"))
            return value.substring(1, value.length - 1);
        return value;
    }

    function splitTextArguments(value) {
        var parts = [];
        var start = 0;
        var parenDepth = 0;
        var bracketDepth = 0;
        var braceDepth = 0;
        var inString = false;
        var escaped = false;
        var i;
        var ch;

        for (i = 0; i < value.length; ++i) {
            ch = value.charAt(i);
            if (escaped) {
                escaped = false;
            } else if (inString && ch === "\\") {
                escaped = true;
            } else if (ch === "\"") {
                inString = !inString;
            } else if (!inString) {
                if (ch === "(")
                    ++parenDepth;
                else if (ch === ")")
                    parenDepth = Math.max(0, parenDepth - 1);
                else if (ch === "[")
                    ++bracketDepth;
                else if (ch === "]")
                    bracketDepth = Math.max(0, bracketDepth - 1);
                else if (ch === "{")
                    ++braceDepth;
                else if (ch === "}")
                    braceDepth = Math.max(0, braceDepth - 1);
                else if (ch === "," && parenDepth === 0 && bracketDepth === 0 && braceDepth === 0) {
                    parts.push(value.substring(start, i));
                    start = i + 1;
                }
            }
        }
        parts.push(value.substring(start));
        return parts;
    }

    function lineOpensText(line) {
        var match = line.match(/\[Text[ \t]*\((.*)\)[ \t]*$/);
        var parts;
        var marker;

        if (!match)
            return null;
        parts = splitTextArguments(match[1]);
        marker = parts.length ? unquoteToken(trim(parts[parts.length - 1])) : "";
        if (marker === "")
            return null;
        return {kind: "text", marker: marker};
    }

    function lineClosesText(line, entry) {
        return entry && typeof entry === "object" && entry.kind === "text" &&
            new RegExp("^" + escapeRegExp(entry.marker) + "\\][ \\t]*$").test(line);
    }

    function functionClosingLine(line) {
        return /^(EndIf|ElseIf|Else|EndWhile|WEnd|EndFor|Next|AgainIf|Until|EndSelect|Case|EndWith)\b/.test(line);
    }

    function functionReopensAfterClose(line) {
        return /^(ElseIf|Else|Case)\b/.test(line);
    }

    function functionBlockOpener(line) {
        if (/^If\b/.test(line))
            return !/\bThen\b/.test(line);
        return /^(While|For|Do|Repeat|Select|With)\b/.test(line);
    }

    function functionSingleOpener(line) {
        return /^If\b/.test(line) && /\bThen[ \t]*$/.test(line);
    }

    function xmlOpeningLine(line) {
        return /^<[@A-Za-z_][A-Za-z0-9_:-]*(?:[ \t][^>]*)?>/.test(line) &&
            !/^<\//.test(line) && !/\/>[ \t]*$/.test(line);
    }

    function xmlClosingLine(line) {
        return /^<\//.test(line);
    }

    function opensContainerKind(line) {
        var match = line.match(/=\s*([\[{])/);
        var start = -1;
        var tail;

        if (match)
            start = match.index + match[0].lastIndexOf(match[1]);
        else {
            match = line.match(/^([\[{])/);
            if (match)
                start = match.index;
        }
        if (start < 0)
            return null;

        tail = line.substring(start);
        if (/^\[\]/.test(tail) || /^\[\./.test(tail))
            return null;
        if (/^\[!/.test(tail))
            return "disabled-square";
        if (/^\{!/.test(tail))
            return "disabled-curly";
        if (/^\[CSV\b/.test(tail) && !/\][ \t]*$/.test(tail))
            return "csv";
        if (/^\[Sequence\b/.test(tail) && !/\][ \t]*$/.test(tail))
            return "sequence";
        if (/^\[Function\b/.test(tail) && !/\][ \t]*$/.test(tail))
            return "function";
        if (/^\[(Array|Data)\b/.test(tail) && !/\][ \t]*$/.test(tail))
            return "array";
        if (/^\[(Scope|Node)\b/.test(tail) && !/\][ \t]*$/.test(tail))
            return "scope";
        if (/^\[Text[ \t]*\(/.test(tail))
            return null;
        // Anonymous array entry, added in dabsic-mode 1.1.1.
        if (/^\[[ \t]*$/.test(tail))
            return "array";
        if (/^\[[A-Za-z_]/.test(tail) && !/\][ \t]*$/.test(tail))
            return "scope";
        if (/^\{[A-Za-z_]/.test(tail) && !/}[ \t]*$/.test(tail))
            return "array";
        return null;
    }

    function updateStack(stack, line) {
        var activeSingle = stackTopKind(stack) === "func-single";
        var topKind = stackTopKind(stack);
        var out;
        var kind;
        var i;

        if (blankOrComment(line))
            return stack.slice();
        if (["disabled-square", "disabled-curly", "disabled-angle"].indexOf(topKind) !== -1) {
            out = stack.slice();
            if (lineClosesDisabled(line, topKind))
                out.pop();
            return out;
        }
        if (topKind === "text") {
            out = stack.slice();
            if (lineClosesText(line, stackTop(stack)))
                out.pop();
            return out;
        }
        if (contextKind(stack) === "csv") {
            out = stack.slice();
            if (/^\]/.test(line))
                out.pop();
            return out;
        }
        if (contextKind(stack) === "sequence") {
            out = stack.slice();
            if (/^\]/.test(line))
                out.pop();
            return out;
        }
        if (contextKind(stack) === "xml") {
            out = stack.slice();
            if (xmlClosingLine(line))
                out.pop();
            else if (xmlOpeningLine(line))
                out.push("xml");
            return out;
        }

        out = stack.slice();
        if (/^\]/.test(line) || /^}/.test(line))
            out.pop();
        else if (insideFunction(out) && functionClosingLine(line))
            out = popFunctionBlock(out);

        if (insideFunction(out) && functionReopensAfterClose(line))
            out.push("func-block");

        kind = lineOpensDisabled(line) || lineOpensText(line) || opensContainerKind(line) ||
            (xmlOpeningLine(line) ? "xml" : null);
        if (kind)
            out.push(kind);

        if (insideFunction(out)) {
            if (functionSingleOpener(line))
                out.push("func-single");
            else if (functionBlockOpener(line))
                out.push("func-block");
        }

        if (activeSingle) {
            for (i = out.length - 1; i >= 0; --i)
                if (entryKind(out[i]) === "func-single") {
                    out.splice(i, 1);
                    break;
                }
        }
        return out;
    }

    function stackBeforeLine(lines, lineIndex) {
        var stack = [];
        var i;
        for (i = 0; i < lineIndex; ++i)
            stack = updateStack(stack, codeLine(lines[i]));
        return stack;
    }

    function indentForLine(stack, line) {
        var depth = stack.length;
        var topKind = stackTopKind(stack);
        var context = contextKind(stack);

        if (blankOrComment(line))
            return null;
        if (["disabled-square", "disabled-curly", "disabled-angle"].indexOf(topKind) !== -1 &&
            lineClosesDisabled(line, topKind))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (topKind === "text" && lineClosesText(line, stackTop(stack)))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (topKind === "text")
            return depth * INDENT_WIDTH;
        if (context === "csv" && /^\]/.test(line))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (context === "sequence" && /^\]/.test(line))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (context === "sequence" && /^[ \t]*([A-Za-z_][A-Za-z0-9_]*):/.test(line))
            return Math.max(0, depth * INDENT_WIDTH - Math.floor(INDENT_WIDTH / 2));
        if (context === "xml" && xmlClosingLine(line))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (/^\]/.test(line) || /^}/.test(line))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        if (insideFunction(stack) && functionClosingLine(line))
            return Math.max(0, depth - 1) * INDENT_WIDTH;
        return depth * INDENT_WIDTH;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function markRange(classes, priorities, start, end, className, priority) {
        var i;
        start = Math.max(0, start);
        end = Math.min(classes.length, end);
        for (i = start; i < end; ++i)
            if (priority >= priorities[i]) {
                priorities[i] = priority;
                classes[i] = className;
            }
    }

    function groupStart(match, groupIndex) {
        var value = match[groupIndex];
        var local;
        if (value === undefined || value === "")
            return -1;
        local = match[0].indexOf(value);
        return local < 0 ? -1 : match.index + local;
    }

    function applyRule(line, classes, priorities, regexp, groupIndex, className, priority) {
        var match;
        var start;
        regexp.lastIndex = 0;
        while ((match = regexp.exec(line)) !== null) {
            start = groupIndex === 0 ? match.index : groupStart(match, groupIndex);
            if (start >= 0)
                markRange(classes, priorities, start, start + match[groupIndex].length, className, priority);
            if (match[0].length === 0)
                ++regexp.lastIndex;
        }
    }

    function scanStringsAndComments(line, classes, priorities, state) {
        var i = 0;
        var start;
        var end;
        var escaped;
        var ch;

        while (i < line.length) {
            if (state.blockComment) {
                start = i;
                end = line.indexOf("*]", i);
                if (end < 0) {
                    markRange(classes, priorities, start, line.length, "dabsic-editor-token-comment", 100);
                    return;
                }
                i = end + 2;
                markRange(classes, priorities, start, i, "dabsic-editor-token-comment", 100);
                state.blockComment = false;
                continue;
            }
            if (line.substr(i, 2) === "[*") {
                start = i;
                end = line.indexOf("*]", i + 2);
                if (end < 0) {
                    markRange(classes, priorities, start, line.length, "dabsic-editor-token-comment", 100);
                    state.blockComment = true;
                    return;
                }
                i = end + 2;
                markRange(classes, priorities, start, i, "dabsic-editor-token-comment", 100);
                continue;
            }
            ch = line.charAt(i);
            if (ch === "\"") {
                start = i++;
                escaped = false;
                while (i < line.length) {
                    ch = line.charAt(i++);
                    if (escaped)
                        escaped = false;
                    else if (ch === "\\")
                        escaped = true;
                    else if (ch === "\"")
                        break;
                }
                markRange(classes, priorities, start, i, "dabsic-editor-token-string", 100);
                continue;
            }
            if (ch === "'") {
                markRange(classes, priorities, i, line.length, "dabsic-editor-token-comment", 100);
                return;
            }
            ++i;
        }
    }

    function tokenizeLine(line, state) {
        var classes = new Array(line.length);
        var priorities = new Array(line.length);
        var keywordRegexp = new RegExp("\\b(?:" + KEYWORDS.join("|") + ")\\b", "g");
        var typeRegexp = new RegExp("\\b(?:" + TYPES.join("|") + ")\\b", "g");
        var constantRegexp = new RegExp("\\b(?:" + CONSTANTS.join("|") + ")\\b", "g");
        var xmlMatch;
        var disabledMatch;
        var specifierRegexp;
        var specifierMatch;
        var specifierStart;
        var bangStart;
        var html = "";
        var currentClass = null;
        var start = 0;
        var i;

        for (i = 0; i < priorities.length; ++i)
            priorities[i] = 0;

        scanStringsAndComments(line, classes, priorities, state);

        xmlMatch = line.match(/^[ \t]*<\/?[@A-Za-z_][A-Za-z0-9_:-]*(?:[ \t][^>]*)?>/);
        if (xmlMatch)
            markRange(classes, priorities, xmlMatch.index, xmlMatch.index + xmlMatch[0].length,
                "dabsic-editor-token-preprocessor", 45);

        disabledMatch = line.match(/^[ \t]*(\[!|\{!|<!)/);
        if (disabledMatch)
            markRange(classes, priorities, groupStart(disabledMatch, 1),
                groupStart(disabledMatch, 1) + disabledMatch[1].length,
                "dabsic-editor-token-comment", 60);

        applyRule(line, classes, priorities, /(^|\s)(@(insert|include|push))\b/g, 2,
            "dabsic-editor-token-preprocessor", 50);
        applyRule(line, classes, priorities, /\[(Function|Array|Data|Sequence|CSV|Scope|Node|Text)\b/g, 1,
            "dabsic-editor-token-preprocessor", 50);
        applyRule(line, classes, priorities, keywordRegexp, 0,
            "dabsic-editor-token-keyword", 40);
        applyRule(line, classes, priorities, constantRegexp, 0,
            "dabsic-editor-token-constant", 40);
        applyRule(line, classes, priorities, /^[ \t]*([A-Za-z_][A-Za-z0-9_]*):/g, 1,
            "dabsic-editor-token-label", 46);
        applyRule(line, classes, priorities, typeRegexp, 0,
            "dabsic-editor-token-type", 40);
        applyRule(line, classes, priorities, /([A-Za-z_][A-Za-z0-9_]*)[ \t]*(?=\()/g, 1,
            "dabsic-editor-token-function", 35);
        applyRule(line, classes, priorities, /[\[{]([A-Za-z_][A-Za-z0-9_]*)/g, 1,
            "dabsic-editor-token-node", 42);
        applyRule(line, classes, priorities, /([A-Za-z_][A-Za-z0-9_]*)[^=\n]*=/g, 1,
            "dabsic-editor-token-variable", 30);

        specifierRegexp = /\b(const|eternal|solid)(!?)/g;
        while ((specifierMatch = specifierRegexp.exec(line)) !== null) {
            specifierStart = groupStart(specifierMatch, 1);
            markRange(classes, priorities, specifierStart, specifierStart + specifierMatch[1].length,
                "dabsic-editor-token-specifier", 41);
            if (specifierMatch[2]) {
                bangStart = specifierMatch.index + specifierMatch[0].lastIndexOf(specifierMatch[2]);
                markRange(classes, priorities, bangStart, bangStart + specifierMatch[2].length,
                    "dabsic-editor-token-disabled-mark", 41);
            }
        }

        for (i = 0; i <= line.length; ++i) {
            if (i === line.length || classes[i] !== currentClass) {
                if (i > start) {
                    if (currentClass)
                        html += "<span class=\"" + currentClass + "\">" + escapeHtml(line.substring(start, i)) + "</span>";
                    else
                        html += escapeHtml(line.substring(start, i));
                }
                start = i;
                currentClass = i < line.length ? classes[i] : null;
            }
        }
        return html;
    }

    function highlight(text) {
        var lines = String(text).split("\n");
        var state = {blockComment: false};
        var html = [];
        var i;
        for (i = 0; i < lines.length; ++i)
            html.push(tokenizeLine(lines[i], state));
        return html.join("\n") + (/\n$/.test(text) ? " " : "");
    }

    function lineInformation(value, position) {
        var lineStart = value.lastIndexOf("\n", position - 1) + 1;
        var lineEnd = value.indexOf("\n", position);
        var lineIndex;
        if (lineEnd < 0)
            lineEnd = value.length;
        lineIndex = value.substring(0, lineStart).split("\n").length - 1;
        return {start: lineStart, end: lineEnd, index: lineIndex};
    }

    function reindentCurrentLine(input, forceBlank) {
        var value = input.value;
        var selectionStart = input.selectionStart;
        var selectionEnd = input.selectionEnd;
        var info = lineInformation(value, selectionStart);
        var line = value.substring(info.start, info.end);
        var lines = value.split("\n");
        var stack = stackBeforeLine(lines, info.index);
        var stripped = codeLine(line);
        var indent = indentForLine(stack, stripped);
        var leading = (line.match(/^[ \t]*/) || [""])[0];
        var replacement;
        var delta;

        if (indent === null && forceBlank)
            indent = indentForLine(stack, "_");
        if (indent === null)
            return false;

        replacement = new Array(indent + 1).join(" ");
        if (leading === replacement)
            return false;
        input.value = value.substring(0, info.start) + replacement + line.substring(leading.length) +
            value.substring(info.end);
        delta = replacement.length - leading.length;

        function mapPosition(position) {
            if (position <= info.start)
                return position;
            if (position <= info.start + leading.length)
                return info.start + replacement.length;
            return position + delta;
        }

        input.setSelectionRange(mapPosition(selectionStart), mapPosition(selectionEnd));
        return true;
    }

    function insertElectricNewline(input) {
        var value = input.value;
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var info = lineInformation(value, start);
        var prefix = value.substring(info.start, start);
        var lines = value.split("\n");
        var stack = stackBeforeLine(lines, info.index);
        var updated = updateStack(stack, codeLine(prefix));
        var indent = indentForLine(updated, "_");
        var insertion;
        var position;

        if (indent === null)
            indent = updated.length * INDENT_WIDTH;
        insertion = "\n" + new Array(indent + 1).join(" ");
        input.value = value.substring(0, start) + insertion + value.substring(end);
        position = start + insertion.length;
        input.setSelectionRange(position, position);
    }

    function currentLineNeedsElectricIndent(input) {
        var info = lineInformation(input.value, input.selectionStart);
        var line = codeLine(input.value.substring(info.start, info.end));
        return /^([\]}]|EndIf\b|ElseIf\b|Else\b|EndWhile\b|WEnd\b|EndFor\b|Next\b|AgainIf\b|Until\b|EndSelect\b|Case\b|EndWith\b|<\/)/.test(line);
    }

    function editorSpan(className, value) {
        return "<span class=\"" + className + "\">" + escapeHtml(value) + "</span>";
    }

    function jsonHighlight(text) {
        var value = String(text);
        var html = "";
        var i = 0;
        var start;
        var ch;
        var escaped;
        var match;
        var rest;
        var lookahead;
        var className;

        while (i < value.length) {
            ch = value.charAt(i);
            if (ch === "\"") {
                start = i++;
                escaped = false;
                while (i < value.length) {
                    ch = value.charAt(i++);
                    if (escaped)
                        escaped = false;
                    else if (ch === "\\")
                        escaped = true;
                    else if (ch === "\"")
                        break;
                }
                lookahead = i;
                while (lookahead < value.length && /[ \t\r\n]/.test(value.charAt(lookahead)))
                    ++lookahead;
                className = value.charAt(lookahead) === ":"
                    ? "dabsic-editor-token-variable"
                    : "dabsic-editor-token-string";
                html += editorSpan(className, value.substring(start, i));
                continue;
            }

            rest = value.substring(i);
            match = rest.match(/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/);
            if (match) {
                html += editorSpan("dabsic-editor-token-constant", match[0]);
                i += match[0].length;
                continue;
            }
            match = rest.match(/^(?:true|false|null)\b/);
            if (match) {
                html += editorSpan("dabsic-editor-token-keyword", match[0]);
                i += match[0].length;
                continue;
            }
            html += escapeHtml(ch);
            ++i;
        }
        return html + (/\n$/.test(value) ? " " : "");
    }

    function jsonStack(text) {
        var stack = [];
        var inString = false;
        var escaped = false;
        var i;
        var ch;
        var top;

        for (i = 0; i < text.length; ++i) {
            ch = text.charAt(i);
            if (inString) {
                if (escaped)
                    escaped = false;
                else if (ch === "\\")
                    escaped = true;
                else if (ch === "\"")
                    inString = false;
                continue;
            }
            if (ch === "\"") {
                inString = true;
                continue;
            }
            if (ch === "{" || ch === "[") {
                stack.push(ch);
                continue;
            }
            if (ch !== "}" && ch !== "]")
                continue;
            top = stack.length ? stack[stack.length - 1] : "";
            if ((ch === "}" && top === "{") || (ch === "]" && top === "["))
                stack.pop();
        }
        return stack;
    }

    function xmlNameChar(ch) {
        return /[A-Za-z0-9_.:-]/.test(ch);
    }

    function scanXmlTokens(text) {
        var tokens = [];
        var i = 0;
        var end;
        var j;
        var startName;
        var name;
        var quote;
        var ch;
        var closing;
        var raw;

        while (i < text.length) {
            if (text.substr(i, 4) === "<!--") {
                end = text.indexOf("-->", i + 4);
                end = end < 0 ? text.length : end + 3;
                tokens.push({kind: "comment", start: i, end: end});
                i = end;
                continue;
            }
            if (text.substr(i, 9) === "<![CDATA[") {
                end = text.indexOf("]]>", i + 9);
                end = end < 0 ? text.length : end + 3;
                tokens.push({kind: "cdata", start: i, end: end});
                i = end;
                continue;
            }
            if (text.substr(i, 2) === "<?") {
                end = text.indexOf("?>", i + 2);
                end = end < 0 ? text.length : end + 2;
                tokens.push({kind: "meta", start: i, end: end});
                i = end;
                continue;
            }
            if (text.charAt(i) !== "<") {
                ++i;
                continue;
            }

            j = i + 1;
            while (j < text.length && /[ \t\r\n]/.test(text.charAt(j)))
                ++j;
            if (text.charAt(j) === "!") {
                quote = "";
                ++j;
                while (j < text.length) {
                    ch = text.charAt(j);
                    if (quote) {
                        if (ch === quote)
                            quote = "";
                    } else if (ch === "\"" || ch === "'") {
                        quote = ch;
                    } else if (ch === ">") {
                        ++j;
                        break;
                    }
                    ++j;
                }
                tokens.push({kind: "meta", start: i, end: j});
                i = j;
                continue;
            }

            closing = false;
            if (text.charAt(j) === "/") {
                closing = true;
                ++j;
                while (j < text.length && /[ \t\r\n]/.test(text.charAt(j)))
                    ++j;
            }
            startName = j;
            while (j < text.length && xmlNameChar(text.charAt(j)))
                ++j;
            if (j === startName) {
                ++i;
                continue;
            }
            name = text.substring(startName, j);
            quote = "";
            while (j < text.length) {
                ch = text.charAt(j);
                if (quote) {
                    if (ch === quote)
                        quote = "";
                } else if (ch === "\"" || ch === "'") {
                    quote = ch;
                } else if (ch === ">") {
                    ++j;
                    break;
                }
                ++j;
            }
            raw = text.substring(i, j);
            tokens.push({
                kind: "tag",
                start: i,
                end: j,
                name: name,
                closing: closing,
                selfClosing: !closing && /\/[ \t\r\n]*>$/.test(raw)
            });
            i = j;
        }
        return tokens;
    }

    function xmlHighlight(text) {
        var value = String(text);
        var tokens = scanXmlTokens(value);
        var html = "";
        var cursor = 0;
        var i;
        var token;
        var className;

        for (i = 0; i < tokens.length; ++i) {
            token = tokens[i];
            if (token.start < cursor)
                continue;
            html += escapeHtml(value.substring(cursor, token.start));
            if (token.kind === "comment")
                className = "dabsic-editor-token-comment";
            else if (token.kind === "cdata")
                className = "dabsic-editor-token-string";
            else
                className = "dabsic-editor-token-preprocessor";
            html += editorSpan(className, value.substring(token.start, token.end));
            cursor = token.end;
        }
        html += escapeHtml(value.substring(cursor));
        return html + (/\n$/.test(value) ? " " : "");
    }

    function xmlDepth(text) {
        var tokens = scanXmlTokens(text);
        var depth = 0;
        var i;
        var token;

        for (i = 0; i < tokens.length; ++i) {
            token = tokens[i];
            if (token.kind !== "tag")
                continue;
            if (token.closing)
                depth = Math.max(0, depth - 1);
            else if (!token.selfClosing)
                ++depth;
        }
        return depth;
    }

    function replaceCurrentIndent(input, indent, forceBlank) {
        var value = input.value;
        var selectionStart = input.selectionStart;
        var selectionEnd = input.selectionEnd;
        var info = lineInformation(value, selectionStart);
        var line = value.substring(info.start, info.end);
        var leading = (line.match(/^[ \t]*/) || [""])[0];
        var body = line.substring(leading.length);
        var replacement;
        var delta;

        if (!forceBlank && body === "")
            return false;
        replacement = new Array(Math.max(0, indent) + 1).join(" ");
        if (leading === replacement)
            return false;

        input.value = value.substring(0, info.start) + replacement + body + value.substring(info.end);
        delta = replacement.length - leading.length;

        function mapPosition(position) {
            if (position <= info.start)
                return position;
            if (position <= info.start + leading.length)
                return info.start + replacement.length;
            return position + delta;
        }

        input.setSelectionRange(mapPosition(selectionStart), mapPosition(selectionEnd));
        return true;
    }

    function insertNewlineAtDepth(input, depth) {
        var value = input.value;
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var insertion = "\n" + new Array(Math.max(0, depth) * INDENT_WIDTH + 1).join(" ");
        var position;

        input.value = value.substring(0, start) + insertion + value.substring(end);
        position = start + insertion.length;
        input.setSelectionRange(position, position);
    }

    function insertSpaces(input) {
        var value = input.value;
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var insertion = new Array(INDENT_WIDTH + 1).join(" ");

        input.value = value.substring(0, start) + insertion + value.substring(end);
        input.setSelectionRange(start + insertion.length, start + insertion.length);
    }

    function jsonReindentCurrentLine(input, forceBlank) {
        var info = lineInformation(input.value, input.selectionStart);
        var line = input.value.substring(info.start, info.end);
        var depth = jsonStack(input.value.substring(0, info.start)).length;
        if (/^[ \t]*[}\]]/.test(line))
            depth = Math.max(0, depth - 1);
        return replaceCurrentIndent(input, depth * INDENT_WIDTH, forceBlank);
    }

    function jsonInsertElectricNewline(input) {
        insertNewlineAtDepth(input, jsonStack(input.value.substring(0, input.selectionStart)).length);
    }

    function jsonCurrentLineNeedsElectricIndent(input) {
        var info = lineInformation(input.value, input.selectionStart);
        return /^[ \t]*[}\]]/.test(input.value.substring(info.start, info.end));
    }

    function xmlReindentCurrentLine(input, forceBlank) {
        var info = lineInformation(input.value, input.selectionStart);
        var line = input.value.substring(info.start, info.end);
        var depth = xmlDepth(input.value.substring(0, info.start));
        if (/^[ \t]*<\//.test(line))
            depth = Math.max(0, depth - 1);
        return replaceCurrentIndent(input, depth * INDENT_WIDTH, forceBlank);
    }

    function xmlInsertElectricNewline(input) {
        insertNewlineAtDepth(input, xmlDepth(input.value.substring(0, input.selectionStart)));
    }

    function xmlCurrentLineNeedsElectricIndent(input) {
        var info = lineInformation(input.value, input.selectionStart);
        return /^[ \t]*<\//.test(input.value.substring(info.start, info.end));
    }

    function editorMode(name) {
        name = String(name || "dab").toLowerCase();
        if (name === "json")
            return {
                highlight: jsonHighlight,
                reindent: jsonReindentCurrentLine,
                insertNewline: jsonInsertElectricNewline,
                needsElectricIndent: jsonCurrentLineNeedsElectricIndent,
                textTab: false
            };
        if (name === "xml")
            return {
                highlight: xmlHighlight,
                reindent: xmlReindentCurrentLine,
                insertNewline: xmlInsertElectricNewline,
                needsElectricIndent: xmlCurrentLineNeedsElectricIndent,
                textTab: false
            };
        if (name === "txt")
            return {
                highlight: function (text) {
                    text = String(text);
                    return escapeHtml(text) + (/\n$/.test(text) ? " " : "");
                },
                reindent: null,
                insertNewline: null,
                needsElectricIndent: null,
                textTab: true
            };
        return {
            highlight: highlight,
            reindent: reindentCurrentLine,
            insertNewline: insertElectricNewline,
            needsElectricIndent: currentLineNeedsElectricIndent,
            textTab: false
        };
    }

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

    function attachEditor(root) {
        var input = root.querySelector(".dabsic-editor-input");
        var highlightLayer = root.querySelector(".dabsic-editor-highlight");
        var highlightCode = highlightLayer ? highlightLayer.querySelector("code") : null;
        var saveButton = root.querySelector(".dabsic-editor-save");
        var stateBox = root.querySelector(".dabsic-editor-state");
        var messageBox = root.querySelector(".dabsic-editor-message");
        var mode = editorMode(root.getAttribute("data-editor-mode") || "dab");
        var baselineContent;
        var baselineHash;
        var saving = false;
        var savedTimer = null;

        if (!input || !highlightLayer || !highlightCode || !saveButton)
            return;

        baselineContent = input.value;
        baselineHash = root.getAttribute("data-hash") || "";

        function label(name, fallback) {
            return root.getAttribute("data-" + name) || fallback;
        }

        function render() {
            highlightCode.innerHTML = mode.highlight(input.value);
            highlightLayer.scrollTop = input.scrollTop;
            highlightLayer.scrollLeft = input.scrollLeft;
        }

        function setMessage(message, kind) {
            messageBox.textContent = message || "";
            messageBox.classList.remove("is-error", "is-success");
            if (message)
                messageBox.classList.add(kind === "success" ? "is-success" : "is-error");
        }

        function isDirty() {
            return input.value !== baselineContent;
        }

        function setState(kind, text) {
            stateBox.classList.remove("is-dirty", "is-saving", "is-saved");
            if (kind)
                stateBox.classList.add("is-" + kind);
            stateBox.textContent = text;
        }

        function updateDirtyState() {
            var dirty = isDirty();
            root.classList.toggle("is-dirty", dirty);
            root.classList.toggle("is-saving", saving);
            saveButton.disabled = !dirty || saving;
            if (saving)
                setState("saving", label("saving-label", "Sauvegarde…"));
            else if (dirty)
                setState("dirty", label("dirty-label", "Modifié"));
            else
                setState("", label("clean-label", "Aucune modification"));
        }

        function afterEdit() {
            render();
            updateDirtyState();
        }

        function save() {
            var contentToSave;
            var payload;

            if (saving || !isDirty())
                return;
            if (!window.confirm(label("confirm-save", "Enregistrer les modifications ?")))
                return;

            contentToSave = input.value;
            payload = {
                file: root.getAttribute("data-file") || "",
                content: contentToSave,
                hash: baselineHash
            };
            try {
                var extra = JSON.parse(root.getAttribute("data-extra-fields") || "{}");
                Object.keys(extra).forEach(function (key) {
                    payload[key] = extra[key];
                });
            } catch (error) {
                setMessage(label("context-error", "Contexte d’édition invalide."), "error");
                return;
            }

            saving = true;
            setMessage("", "error");
            updateDirtyState();

            fetch(root.getAttribute("data-save-url"), {
                method: root.getAttribute("data-save-method") || "POST",
                credentials: "same-origin",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(payload)
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
                baselineContent = contentToSave;
                baselineHash = packet.hash || baselineHash;
                root.setAttribute("data-hash", baselineHash);
                saving = false;
                setMessage(packet.msg || label("saved-label", "Document sauvegardé."), "success");
                updateDirtyState();
                if (!isDirty()) {
                    setState("saved", label("saved-label", "Document sauvegardé."));
                    if (savedTimer)
                        window.clearTimeout(savedTimer);
                    savedTimer = window.setTimeout(updateDirtyState, 1800);
                }
                root.dispatchEvent(new CustomEvent("dabsic-editor-saved", {
                    bubbles: true,
                    detail: packet
                }));
            }).catch(function (error) {
                saving = false;
                setMessage(error && error.message ? error.message :
                    label("network-error", "Erreur réseau."), "error");
                updateDirtyState();
            });
        }

        input.addEventListener("scroll", function () {
            highlightLayer.scrollTop = input.scrollTop;
            highlightLayer.scrollLeft = input.scrollLeft;
        });

        input.addEventListener("input", function () {
            if (mode.needsElectricIndent && mode.needsElectricIndent(input) && mode.reindent)
                mode.reindent(input, false);
            afterEdit();
        });

        input.addEventListener("keydown", function (event) {
            if (event.key === "Tab") {
                event.preventDefault();
                if (mode.textTab)
                    insertSpaces(input);
                else if (mode.reindent)
                    mode.reindent(input, true);
                afterEdit();
            } else if (event.key === "Enter" && mode.insertNewline) {
                event.preventDefault();
                mode.insertNewline(input);
                afterEdit();
            } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
                event.preventDefault();
                save();
            }
        });

        saveButton.addEventListener("click", save);

        window.addEventListener("beforeunload", function (event) {
            if (!isDirty())
                return;
            event.preventDefault();
            event.returnValue = label("unsaved-warning", "Des modifications ne sont pas sauvegardées.");
            return event.returnValue;
        });

        render();
        updateDirtyState();
        if (root.getAttribute("data-autofocus") !== "0")
            input.focus();
        root.setAttribute("data-dabsic-editor-attached", "1");
    }

    window.DabsicLanguage = {
        codeLine: codeLine,
        updateStack: updateStack,
        stackBeforeLine: stackBeforeLine,
        indentForLine: indentForLine,
        highlight: highlight,
        indentWidth: INDENT_WIDTH
    };

    window.DabsicEditor = window.DabsicEditor || {};
    window.DabsicEditor.attach = function (root) {
        if (!root || root.getAttribute("data-dabsic-editor-attached") === "1")
            return;
        attachEditor(root);
    };
    window.DabsicEditor.attachAll = function (scope) {
        var roots = (scope || document).querySelectorAll(".dabsic-editor-root[data-dabsic-editor]");
        Array.prototype.forEach.call(roots, window.DabsicEditor.attach);
    };
    window.DabsicEditor.attachAll(document);
}());

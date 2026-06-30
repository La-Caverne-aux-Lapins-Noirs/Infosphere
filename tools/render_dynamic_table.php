<?php

function render_dynamic_table($table_id, $fields, $rows)
{
    static $instance_id = 0;
    $instance_id++;

    $js_ns = preg_replace('/[^a-zA-Z0-9_]/', '_', $table_id) . "_" . $instance_id;

    // Reset
    echo '<input class="dynamic_table_button" type="button" value="Reset filtres" onclick="' . $js_ns . '_reset_filters();" />';
    echo '<input class="dynamic_table_button" type="button" value="Toggle view deleted" onclick="'.$js_ns.'_toggle_view_deleted();" />';
    
    echo '<table id="' . htmlspecialchars($table_id) . '" class="dynamic_table">';

    /*
    ** Header
    */
    echo '<tr>';
    foreach ($fields as $field)
    {
        $label = $field["label"] ?? "";
        $type = $field["type"] ?? "text";
        $width = $field["width"] ?? null;
        $style = "";

        if ($width !== null)
            $style .= 'width: ' . htmlspecialchars($width) . ';';

        if ($type !== "misc")
            $style .= 'cursor: pointer; user-select: none;';

        echo '<th';

        if (!empty($style))
            echo ' style="' . $style . '"';

        if ($type !== "misc")
            echo ' onclick="' . $js_ns . '_order_by(\'' . addslashes($field["name"]) . '\', this);"';

        if (!empty($field["colspan"]))
            echo ' colspan="' . (int)$field["colspan"] . '"';

        echo '>';

        echo $label;
        echo '</th>';
    }
    echo '</tr>';

    /*
    ** Filters row
    */
    echo '<tr>';
    foreach ($fields as $field)
    {
        $name = $field["name"] ?? "";
        $type = $field["type"] ?? "text";
        $filter = $field["filter"] ?? true;

        if ($type === "misc" || !$filter)
        {
            $colspan = (int)($field["colspan"] ?? 1);
            echo '<td' . ($colspan > 1 ? ' colspan="' . $colspan . '"' : '') . '></td>';
            continue;
        }

        echo '<td>';

        if ($type === "select")
        {
            echo '<select id="' . $js_ns . '_filter_' . htmlspecialchars($name) . '" onchange="' . $js_ns . '_apply_filters();">';
            echo '<option value=""></option>';

            foreach (($field["options"] ?? []) as $value => $label)
                echo '<option value="' . htmlspecialchars((string)$value) . '">' . htmlspecialchars($label) . '</option>';

            echo '</select>';
        }
        else
        {
            echo '<input type="text" id="' . $js_ns . '_filter_' . htmlspecialchars($name) . '" onkeyup="' . $js_ns . '_apply_filters();" />';
        }

        echo '</td>';
    }
    echo '</tr>';

    /*
    ** Rows
    */
    foreach ($rows as $row)
    {
        echo '<tr class="'.$js_ns.'_row '.($row["deleted"] ? "deleted" : "").'"';

        foreach ($fields as $field)
        {
            $type = $field["type"] ?? "text";

            if ($type === "misc")
                continue;

            $name = $field["name"];
            $raw = "";

            if (isset($field["raw"]) && is_callable($field["raw"]))
                $raw = $field["raw"]($row);
            else if (array_key_exists($name, $row))
                $raw = $row[$name];

            if (is_string($raw))
                $raw = mb_strtolower($raw);

            echo ' data-' . htmlspecialchars($name) . '="' . htmlspecialchars((string)$raw) . '"';

	    if ($name == "id")
		echo htmlspecialchars($name).'="'.$table_id.htmlspecialchars((string)$raw) . '"';
        }

        echo '>';

        foreach ($fields as $field)
        {
            $type = $field["type"] ?? "text";
            $name = $field["name"] ?? "";
            $classes = [];
	    $copyable = $field["copyable"] ?? "";
	    
	    if ($copyable)
		$classes[] = "copyable";

            if (isset($field["cell_class"]))
            {
                if (is_callable($field["cell_class"]))
                    $cell_class = $field["cell_class"]($row);
                else
                    $cell_class = $field["cell_class"];
                foreach (explode(" ", (string)$cell_class) as $class)
                    if (trim($class) != "")
                        $classes[] = trim($class);
            }

            $class = count($classes) ? ' class="'.htmlspecialchars(implode(" ", $classes)).'"' : '';
            $colspan = (int)($field["colspan"] ?? 1);
            echo '<td' . ($colspan > 1 ? ' colspan="' . $colspan . '"' : '') . $class . '>';

            if (isset($field["render"]) && is_callable($field["render"]))
                echo $field["render"]($row);
            else if ($type !== "misc")
                echo htmlspecialchars((string)($row[$name] ?? ""));
            else
                echo '';

            echo '</td>';
        }

        echo '</tr>';
    }

    echo '</table>';

    /*
    ** JS scoped per table
    */
    ?>
    <script>
    var <?=$js_ns; ?>_current_order_field = null;
    var <?=$js_ns; ?>_current_order_direction = 0;

    function <?=$js_ns; ?>_get_table()
    {
        return document.getElementById("<?=addslashes($table_id); ?>");
    }

    function <?=$js_ns; ?>_get_rows()
    {
        return Array.from(<?=$js_ns; ?>_get_table().querySelectorAll("tr.<?=$js_ns; ?>_row"));
    }

    function <?=$js_ns; ?>_clear_sort_classes()
    {
        <?=$js_ns; ?>_get_table().querySelectorAll("th").forEach(function(th)
        {
            th.classList.remove("sort_asc");
            th.classList.remove("sort_desc");
        });
    }

    function <?=$js_ns; ?>_update_sort_classes(th)
    {
        <?=$js_ns; ?>_clear_sort_classes();

        if (!th || <?=$js_ns; ?>_current_order_direction === 0)
            return;

        if (<?=$js_ns; ?>_current_order_direction === 1)
            th.classList.add("sort_asc");
        else
            th.classList.add("sort_desc");
    }

    function <?=$js_ns; ?>_order_by(field, th)
    {
        if (<?=$js_ns; ?>_current_order_field !== field)
        {
            <?=$js_ns; ?>_current_order_field = field;
            <?=$js_ns; ?>_current_order_direction = 1;
        }
        else if (<?=$js_ns; ?>_current_order_direction === 1)
            <?=$js_ns; ?>_current_order_direction = -1;
        else if (<?=$js_ns; ?>_current_order_direction === -1)
            <?=$js_ns; ?>_current_order_direction = 0;
        else
            <?=$js_ns; ?>_current_order_direction = 1;

        let table = <?=$js_ns; ?>_get_table();
        let rows = <?=$js_ns; ?>_get_rows();

        rows.sort(function(a, b)
        {
            let va;
            let vb;

            if (<?=$js_ns; ?>_current_order_direction === 0)
            {
                va = parseInt(a.dataset.id || "0", 10);
                vb = parseInt(b.dataset.id || "0", 10);
                return va - vb;
            }

            va = a.dataset[field] || "";
            vb = b.dataset[field] || "";

            if (!isNaN(va) && !isNaN(vb) && va !== "" && vb !== "")
                return (parseFloat(va) - parseFloat(vb)) * <?=$js_ns; ?>_current_order_direction;

            return va.localeCompare(vb, "fr", {sensitivity: "base"}) * <?=$js_ns; ?>_current_order_direction;
        });

        rows.forEach(function(row)
        {
            table.appendChild(row);
        });

        <?=$js_ns; ?>_update_sort_classes(th);
    }

    function <?=$js_ns; ?>_apply_filters()
    {
        let rows = <?=$js_ns; ?>_get_rows();

        rows.forEach(function(row)
        {
            let visible = true;

            <?php foreach ($fields as $field):
                $type = $field["type"] ?? "text";
                $filter = $field["filter"] ?? true;
                if ($type === "misc" || !$filter)
                    continue;
                $name = $field["name"];
            ?>
            {
                let input = document.getElementById("<?=$js_ns; ?>_filter_<?=htmlspecialchars($name); ?>");
                let filter_value = "";

                if (input)
                    filter_value = (input.value || "").toLowerCase().trim();

                let row_value = (row.dataset["<?=addslashes($name); ?>"] || "").toLowerCase();

                <?php if ($type === "select"): ?>
                if (filter_value !== "" && row_value !== filter_value)
                    visible = false;
                <?php else: ?>
                if (filter_value !== "" && !row_value.includes(filter_value))
                    visible = false;
                <?php endif; ?>
            }
            <?php endforeach; ?>

            if (visible)
                row.classList.remove("hidden_row");
            else
                row.classList.add("hidden_row");
        });
    }

    function <?=$js_ns; ?>_reset_filters()
    {
        <?php foreach ($fields as $field):
            $type = $field["type"] ?? "text";
            $filter = $field["filter"] ?? true;
            if ($type === "misc" || !$filter)
                continue;
            $name = $field["name"];
        ?>
        {
            let input = document.getElementById("<?=$js_ns; ?>_filter_<?=htmlspecialchars($name); ?>");
            if (input)
                input.value = "";
        }
        <?php endforeach; ?>

        <?=$js_ns; ?>_current_order_field = null;
        <?=$js_ns; ?>_current_order_direction = 0;
        <?=$js_ns; ?>_clear_sort_classes();

        let table = <?=$js_ns; ?>_get_table();
        let rows = <?=$js_ns; ?>_get_rows();

        rows.sort(function(a, b)
        {
            return (parseInt(a.dataset.id || "0", 10) - parseInt(b.dataset.id || "0", 10));
        });

        rows.forEach(function(row)
        {
            row.classList.remove("hidden_row");
            table.appendChild(row);
        });
    }

    function <?=$js_ns; ?>_toggle_view_deleted()
    {
        <?=$js_ns; ?>_get_table().querySelectorAll(".deleted").forEach(function(element)
        {
            element.classList.toggle("visible");
        });
    }
    </script>
    <?php
}


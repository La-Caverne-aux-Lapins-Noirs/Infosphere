(function () {
  let opened = null;

  function close_bigselect() {
    if (opened) {
      opened.popup.remove();
      opened = null;
    }
  }

  function selected_label(select) {
    const opt = select.selectedOptions && select.selectedOptions[0];
    return opt ? opt.textContent.trim() : "";
  }

  function copy_option_look(option, button) {
    if (option.className) {
      for (const cls of option.className.split(/\s+/)) {
        if (cls)
          button.classList.add(cls);
      }
    }

    const style = option.getAttribute("style");
    if (style)
      button.style.cssText += ";" + style;
  }

  function collect_items(select) {
    const items = [];

    for (const child of select.children) {
      if (child.tagName === "OPTGROUP") {
        items.push({
          type: "group",
          label: child.label || ""
        });

        for (const option of child.children) {
          if (option.tagName === "OPTION") {
            items.push({
              type: "option",
              option: option
            });
          }
        }
      } else if (child.tagName === "OPTION") {
        items.push({
          type: "option",
          option: child
        });
      }
    }

    return items;
  }

  function position_popup(popup, button, wanted_width) {
    const rect = button.getBoundingClientRect();
    const margin = 8;
    const gap = 4;
    const viewport_width = document.documentElement.clientWidth || window.innerWidth;
    const viewport_height = document.documentElement.clientHeight || window.innerHeight;

    const width = Math.max(1, Math.min(
      wanted_width,
      viewport_width - margin * 2
    ));

    let left = rect.left;
    if (left + width > viewport_width - margin)
      left = viewport_width - width - margin;
    if (left < margin)
      left = margin;

    popup.style.width = width + "px";
    popup.style.left = Math.round(left) + "px";

    // Mesurer d'abord la hauteur réellement nécessaire (dans la limite
    // générale de 520 px), puis choisir le côté qui peut effectivement la
    // contenir. L'ancien test sur un seuil fixe de 220 px pouvait ouvrir une
    // grille de 500 px vers le bas alors qu'il ne restait que ~250 px.
    const viewport_max_height = Math.max(1, viewport_height - margin * 2);
    popup.style.maxHeight = Math.min(520, viewport_max_height) + "px";
    popup.style.top = margin + "px";

    let popup_height = popup.getBoundingClientRect().height;
    const space_below = Math.max(0, viewport_height - rect.bottom - gap - margin);
    const space_above = Math.max(0, rect.top - gap - margin);
    const open_above = popup_height > space_below && space_above > space_below;
    const available_height = open_above ? space_above : space_below;

    if (popup_height > available_height) {
      popup.style.maxHeight = Math.max(1, Math.min(520, available_height)) + "px";
      popup_height = popup.getBoundingClientRect().height;
    }

    let top;
    if (open_above)
      top = rect.top - gap - popup_height;
    else
      top = rect.bottom + gap;

    // Dernier garde-fou : même avec des tailles/zooms inhabituels, la popup
    // reste intégralement dans le viewport.
    top = Math.max(margin, Math.min(top, viewport_height - popup_height - margin));
    popup.style.top = Math.round(top) + "px";
  }

  function open_bigselect(select, display_button) {
    close_bigselect();

    const popup = document.createElement("div");
    popup.className = "bigselect-popup";

    const columns = parseInt(select.dataset.columns || "3", 10);
    const width = parseInt(select.dataset.popupWidth || "850", 10);

    popup.style.setProperty("--bigselect-columns", String(columns));

    for (const item of collect_items(select)) {
      if (item.type === "group") {
        const group = document.createElement("div");
        group.className = "bigselect-group";
        group.textContent = item.label;
        popup.appendChild(group);
        continue;
      }

      const option = item.option;
      const button = document.createElement("button");

      button.type = "button";
      button.className = "bigselect-option";
      button.textContent = option.textContent;
      button.disabled = option.disabled;
      button.setAttribute("aria-selected", option.selected ? "true" : "false");

      copy_option_look(option, button);

      button.addEventListener("click", function (ev) {
        ev.preventDefault();
        ev.stopPropagation();

        if (option.disabled)
          return;

        select.value = option.value;

        display_button.textContent = selected_label(select);

        select.dispatchEvent(new Event("input", { bubbles: true }));
        select.dispatchEvent(new Event("change", { bubbles: true }));

        close_bigselect();
      });

      popup.appendChild(button);
    }

    document.body.appendChild(popup);
    position_popup(popup, display_button, width);

    opened = {
      select: select,
      popup: popup
    };
  }

  function upgrade_bigselect(select) {
    if (select.dataset.bigselectReady === "1")
      return;

    select.dataset.bigselectReady = "1";

    const wrapper = document.createElement("span");
    wrapper.className = "bigselect-wrap";

    const button = document.createElement("button");
    button.type = "button";
    button.className = "bigselect-button";
    button.textContent = selected_label(select);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    wrapper.appendChild(button);

    select.classList.add("bigselect-native");

    button.addEventListener("click", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();

      if (opened && opened.select === select) {
        close_bigselect();
        return;
      }

      open_bigselect(select, button);
    });

    select.addEventListener("change", function () {
      button.textContent = selected_label(select);
    });
  }

  function init_bigselects(root) {
    root = root || document;
    root.querySelectorAll("select.bigselect").forEach(upgrade_bigselect);
  }

  document.addEventListener("DOMContentLoaded", function () {
    init_bigselects(document);

    const observer = new MutationObserver(function (mutations) {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node.nodeType === 1)
            init_bigselects(node);
        }
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  });

  document.addEventListener("click", close_bigselect);

  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape")
      close_bigselect();
  });

  window.addEventListener("resize", close_bigselect);
  window.addEventListener("scroll", close_bigselect, true);

  window.init_bigselects = init_bigselects;
})();


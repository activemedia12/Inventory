// ── Tab switching ────────────────────────────────────────────────
function switchTab(tabId) {
  document
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.toggle("active", b.dataset.tab === tabId));
  document
    .querySelectorAll(".tab-content-pane")
    .forEach((p) => p.classList.toggle("active", p.id === "tab-" + tabId));
  document.getElementById("activeTabInput").value = tabId;
  // Scroll the active tab button into view
  const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
  if (activeBtn)
    activeBtn.scrollIntoView({
      behavior: "smooth",
      block: "nearest",
      inline: "center",
    });
}

// ── Digital paper sub-tabs ───────────────────────────────────────
function switchDpTab(ptKey) {
  document
    .querySelectorAll(".dp-tab-btn")
    .forEach((b) => b.classList.toggle("active", b.dataset.dptab === ptKey));
  document
    .querySelectorAll(".dp-content")
    .forEach((p) => p.classList.toggle("active", p.id === "dptab-" + ptKey));
}

// ── Tab content visibility (CSS-based) ──────────────────────────
const style = document.createElement("style");
style.textContent =
  ".tab-content-pane { display: none; } .tab-content-pane.active { display: block; }";
document.head.appendChild(style);

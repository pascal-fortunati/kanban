(function () {
    var badge = document.getElementById("notif-badge");
    var panel = document.getElementById("notif-panel");
    var msg = document.getElementById("notif-message");
    if (!badge) return;
    if (!window.IS_LOGGED) { try { badge.style.display = "none"; } catch (e) {} return; }
    var last = 0;
    function esc(s) {
        return escapeHtml(s);
    }
    function showToast() {
        if (window.Swal) {
            window.Swal.fire({ toast: true, position: "top-end", showConfirmButton: false, timer: 3000, timerProgressBar: true, background: "#1f2937", color: "#e5e7eb", iconColor: "#61afef", icon: "info", title: "Nouvelles notifications" });
            return;
        }
        var t = document.createElement("div");
        t.className = "fixed bottom-4 right-4 bg-blue-600 text-white px-3 py-2 rounded shadow";
        t.textContent = "Nouvelles notifications";
        document.body.appendChild(t);
        setTimeout(function () {
            if (t.parentNode) t.parentNode.removeChild(t);
        }, 3000);
    }
    function setCount(c) {
        var n = parseInt(c || 0, 10);
        badge.textContent = String(n);
        badge.style.display = n > 0 ? "block" : "none";
        var btn = document.getElementById("notif-btn");
        if (btn) {
            btn.classList.remove("bg-blue-600", "text-white", "hover:bg-blue-500");
            btn.classList.remove("bg-gray-800", "text-gray-200", "hover:bg-gray-700");
            if (n > 0) {
                btn.classList.add("bg-blue-600", "text-white", "hover:bg-blue-500");
            } else {
                btn.classList.add("bg-gray-800", "text-gray-200", "hover:bg-gray-700");
            }
        }
        if (n > last) showToast();
        last = n;
    }
    function poll() {
        var url = (window.APP_BASE || "") + "/dashboard/getNotifications";
        var scan = (window.APP_BASE || "") + "/github/scanCommits?limit=1";
        var now = Date.now();
        if (!window.__lastScanAt) window.__lastScanAt = 0;
        var role = window.USER_ROLE || "guest";
        var doScan = role === "formateur" && now - window.__lastScanAt > 60000;
        var p = doScan
            ? fetch(scan, { credentials: "same-origin" }).then(function () {
                  window.__lastScanAt = Date.now();
                  return fetch(url, { credentials: "same-origin" });
              })
            : fetch(url, { credentials: "same-origin" });
        p.then(function (r) {
            if (!r.ok) throw new Error("x");
            return r.json();
        })
            .then(function (d) {
                setCount(d && typeof d.count !== "undefined" ? d.count : 0);
            })
            .catch(function () {});
    }
    setCount(0);
    setInterval(poll, 10000);
    poll();
    window.openNotifModal = function (e) {
        if (e && e.preventDefault) e.preventDefault();
        var url = (window.APP_BASE || "") + "/dashboard/getNotifications";
        fetch(url, { credentials: "same-origin" })
            .then(function (r) {
                if (!r.ok) throw new Error("x");
                return r.json();
            })
            .then(function (d) {
                var items = d && d.items ? d.items : [];
                items = items.filter(function (n) {
                    return String(n.type || "") !== "commit";
                });
                var html = "";
                if (items.length === 0) {
                    html = '<div class="text-sm text-gray-300">Aucune notification</div>';
                } else {
                    html = '<div class="space-y-2">';
                    items.forEach(function (n) {
                        var m = esc(n.message || "");
                        var dt = formatFrenchDate(n.created_at || "");
                        html +=
                            '<div class="p-3 rounded bg-gray-800/50 border border-gray-700"><div class="text-sm text-gray-100">' +
                            m +
                            '</div><div class="text-xs text-gray-400">' +
                            dt +
                            "</div></div>";
                    });
                    html += "</div>";
                }
                var opts = {
                    title: "Notifications",
                    html: html,
                    showCloseButton: true,
                    showConfirmButton: false,
                    width: 560,
                    icon: "info",
                    iconColor: "#61afef",
                    background: "#1f2937",
                    color: "#e5e7eb",
                };
                if (window.Swal) { window.Swal.fire(opts); }
                items.forEach(function (it) {
                    markRead(it.id);
                });
                setCount(d && typeof d.count !== "undefined" ? d.count : 0);
            })
            .catch(function () {});
    };
    function showLatest() {
        if (!msg) return;
        var url = (window.APP_BASE || "") + "/dashboard/getNotifications";
        fetch(url, { credentials: "same-origin" })
            .then(function (r) {
                if (!r.ok) throw new Error("x");
                return r.json();
            })
            .then(function (d) {
                var items = d && d.items ? d.items : [];
                items = items.filter(function (n) {
                    return String(n.type || "") !== "commit";
                });
                if (items.length > 0) {
                    var n = items[0];
                    msg.innerHTML = "";
                    var t = document.createElement("div");
                    t.className = "text-sm text-gray-100";
                    t.textContent = n.message || "";
                    var s = document.createElement("div");
                    s.className = "text-xs text-gray-400";
                    s.textContent = formatFrenchDate(n.created_at || "");
                    msg.appendChild(t);
                    msg.appendChild(s);
                    items.forEach(function (it) {
                        markRead(it.id);
                    });
                    setCount(d && typeof d.count !== "undefined" ? d.count : 0);
                } else {
                    msg.textContent = "Aucune notification";
                }
            })
            .catch(function () {
                msg.textContent = "";
            });
    }
    function markRead(id) {
        fetch((window.APP_BASE || "") + "/dashboard/markNotificationRead", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            credentials: "same-origin",
            body: "id=" + encodeURIComponent(id || 0) + "&csrf_token=" + encodeURIComponent(window.CSRF_TOKEN || ''),
        })
            .then(function () {})
            .catch(function () {});
    }
    window.toggleNotifPanel = function (e) {
        if (e && e.preventDefault) e.preventDefault();
        if (!panel) {
            return;
        }
        var h = panel.classList.contains("hidden");
        if (h) {
            panel.classList.remove("hidden");
            showLatest();
        } else {
            panel.classList.add("hidden");
        }
    };
    window.closeNotifPanel = function () {
        if (panel) panel.classList.add("hidden");
    };
})();